<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\EventRequest;
use App\Models\FavoriteVenue;
use App\Services\EventConflictChecker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use App\Models\Venue;
use App\Models\User;

class OfficeController extends Controller
{
    public function dashboard(Request $request, bool $addEventOnly = false, bool $requestStatusOnly = false, bool $activityOnly = false)
    {
        $user = Auth::user();
        $email = $user->email;

        // ---- Calendar: approved events this office may see ----
        // University-wide offices see every campus; a campus office sees its own campus,
        // events open to all campuses (wherever they're held), and always its own requests.
        $approvedEvents = EventRequest::where('status', 'approved')
            ->get()
            ->filter(fn ($event) => $event->isVisibleTo($user))
            ->values();

        $events = $approvedEvents
            ->sortBy('start_datetime')
            ->map(function ($event) {
                return [
                    'title' => $event->title,
                    'start' => $event->start_datetime,
                    'end' => $event->end_datetime,
                    'description' => $event->description,
                    'venue' => $event->venue_name,
                    'campus' => $event->venueCampus(),
                    'university_wide' => (bool) $event->is_university_wide,
                    'office' => $event->name,
                    'office_filter' => User::UNIVERSITY_OFFICE_ACCOUNT_LABELS[$event->email] ?? 'Other offices',
                ];
            })
            ->values();   // re-index after sortBy so it becomes a JSON list, not an object

        // ---- This office's own requests (unfiltered) ----
        $ownRequests = EventRequest::where('email', $email)
            ->orderBy('created_at', 'desc')
            ->get();

        $officeRequests = $ownRequests; // used by the quick "Your request status" list

        // ---- Initial values for the Request Status filters (filtering runs client-side) ----
        $filterStatus = $request->query('status', 'all');
        $filterQuery = trim((string) $request->query('q', ''));
        $filterFrom = $request->query('from');
        $filterTo = $request->query('to');

        // ---- Summary counts ----
        $totalEvents = $approvedEvents->count();
        $upcomingEvents = $approvedEvents
            ->filter(fn ($event) => $event->start_datetime >= now())
            ->count();

        $pendingCount = $ownRequests->whereIn('status', ['pending', 'conflict'])->count();
        $approvedCount = $ownRequests->where('status', 'approved')->count();
        $rejectedCount = $ownRequests->where('status', 'rejected')->count();
        $cancelledCount = $ownRequests->where('status', 'cancelled')->count();

        // ---- Monthly summary ----
        $monthStart = now()->startOfMonth();
        $submittedThisMonth = $ownRequests->filter(fn ($r) => $r->created_at && $r->created_at->greaterThanOrEqualTo($monthStart))->count();
        $approvedThisMonth = $ownRequests->filter(fn ($r) => $r->status === 'approved' && $r->updated_at && $r->updated_at->greaterThanOrEqualTo($monthStart))->count();

        // ---- Notifications (status changes the office hasn't seen yet) ----
        $unreadNotifications = $ownRequests
            ->whereIn('status', EventRequest::NOTIFIABLE_STATUSES)
            ->whereNull('read_at')
            ->sortByDesc('updated_at')
            ->values();

        // Every notification (read and unread) for the bell dropdown: unread first, then newest
        $allNotifications = $ownRequests
            ->whereIn('status', EventRequest::NOTIFIABLE_STATUSES)
            ->sortBy([
                fn ($a, $b) => ($a->read_at === null ? 0 : 1) <=> ($b->read_at === null ? 0 : 1),
                fn ($a, $b) => $b->updated_at <=> $a->updated_at,
            ])
            ->values();

        // ---- Reminder banner: next approved event within 48 hours ----
        $reminder = $ownRequests
            ->where('status', 'approved')
            ->filter(fn ($r) => $r->start_datetime && $r->start_datetime->isFuture())
            ->sortBy('start_datetime')
            ->first();

        if ($reminder && now()->diffInHours($reminder->start_datetime) > 48) {
            $reminder = null;
        }

        // ---- Favorite venues ----
        $favoriteVenues = FavoriteVenue::where('email', $email)->orderBy('venue_name')->get();

        // ---- Venue names for the request form's autocomplete (exact names matter for conflict checks) ----
        $venueOptions = Venue::orderBy('name')->pluck('name')
            ->merge(EventRequest::distinct()->pluck('venue_name'))
            ->filter()
            ->unique(fn ($name) => mb_strtolower(trim($name)))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        // ---- Recent activity log ----
        $recentActivity = ActivityLog::where('email', $email)->latest()->limit(8)->get();

        return view('office.dashboard', compact(
            'addEventOnly',
            'requestStatusOnly',
            'activityOnly',
            'events',
            'officeRequests',
            'filterStatus',
            'filterQuery',
            'filterFrom',
            'filterTo',
            'totalEvents',
            'upcomingEvents',
            'pendingCount',
            'approvedCount',
            'rejectedCount',
            'cancelledCount',
            'submittedThisMonth',
            'approvedThisMonth',
            'unreadNotifications',
            'allNotifications',
            'reminder',
            'favoriteVenues',
            'recentActivity',
            'venueOptions'
        ));
    }

    public function addEvent(Request $request)
    {
        return $this->dashboard($request, true);
    }

    public function requestStatus(Request $request)
    {
        return $this->dashboard($request, false, true);
    }

    public function recentActivity(Request $request)
    {
        return $this->dashboard($request, false, false, true);
    }

    public function calendar(Request $request)
    {
        return $this->dashboard($request);
    }

    /**
     * Live availability check for the request form (called while the office is typing).
     * Uses the same EventConflictChecker as requestVenue(), so the result always
     * matches what happens on submit.
     */
    public function checkAvailability(Request $request, EventConflictChecker $conflictChecker)
    {
        $isUniversityWide = $request->boolean('is_university_wide');
        $validated = $request->validate([
            'venue_name' => 'required|string|max:255',
            'campus' => $isUniversityWide
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', \Illuminate\Validation\Rule::in(\App\Models\User::CAMPUSES)],
            'is_university_wide' => 'nullable|boolean',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'edit_id' => 'nullable|integer',
        ]);

        $user = Auth::user();
        $venue = trim($validated['venue_name']);
        $campus = $isUniversityWide ? 'All Campus' : $validated['campus'];
        $start = Carbon::parse($validated['start_datetime']);
        $end = Carbon::parse($validated['end_datetime']);
        $this->validateOfficeHours($start, $end);

        // Only exclude a request this office actually owns
        $excludeId = null;
        if (! empty($validated['edit_id'])) {
            $excludeId = EventRequest::where('id', $validated['edit_id'])->where('email', $user->email)->value('id');
        }

        $present = function (EventRequest $event) use ($user) {
            $isOwn = $event->email === $user->email;

            return [
                // Other offices' unapproved requests stay private: show who, not what
                'title' => ($isOwn || $event->status === 'approved') ? $event->title : 'Reserved — awaiting review',
                'office' => $isOwn ? 'Your office' : $event->name,
                'status' => $event->status,
                'is_own' => $isOwn,
                'time' => $this->formatRange($event->start_datetime, $event->end_datetime),
            ];
        };

        $conflicts = $conflictChecker->find($venue, $campus, $start, $end, $excludeId);

        $suggestion = null;
        if ($conflicts->isNotEmpty()) {
            $slot = $conflictChecker->nextAvailableSlot($venue, $campus, $start, $end, $excludeId);
            if ($slot) {
                $suggestion = [
                    'start' => $slot[0]->format('Y-m-d\TH:i'),
                    'end' => $slot[1]->format('Y-m-d\TH:i'),
                    'label' => $this->formatRange($slot[0], $slot[1]),
                ];
            }
        }

        $daySchedule = $conflictChecker
            ->find($venue, $campus, $start->copy()->startOfDay(), $start->copy()->endOfDay(), $excludeId)
            ->sortBy('start_datetime')
            ->values();

        return response()->json([
            'state' => $conflicts->isEmpty() ? 'available' : 'conflict',
            'venue' => $venue,
            'requested' => $this->formatRange($start, $end),
            'in_past' => $start->isPast(),
            'conflicts' => $conflicts->map($present)->values(),
            'suggestion' => $suggestion,
            'day' => [
                'label' => $start->format('l, M j, Y'),
                'events' => $daySchedule->map($present)->values(),
            ],
        ]);
    }

    private function formatRange(Carbon $start, Carbon $end): string
    {
        return $start->isSameDay($end)
            ? $start->format('M j, Y g:i A') . ' – ' . $end->format('g:i A')
            : $start->format('M j, Y g:i A') . ' – ' . $end->format('M j, Y g:i A');
    }

    private function validateOfficeHours(Carbon $start, Carbon $end): void
    {
        if (! EventConflictChecker::isWithinOfficeHours($start, $end)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_datetime' => 'Events must start and end between 8:00 AM and 5:00 PM. Multi-day events are allowed within these hours.',
            ]);
        }
    }

    public function requestVenue(Request $request, EventConflictChecker $conflictChecker)
    {
        $isUniversityWide = $request->boolean('is_university_wide');
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'venue_name' => 'required|string|max:255',
            'event_for' => 'nullable|string|max:5000',
            'attention_to' => 'nullable|string|max:5000',
            'campus' => $isUniversityWide
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', \Illuminate\Validation\Rule::in(\App\Models\User::CAMPUSES)],
            'is_university_wide' => ['nullable', 'boolean'],
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'description' => 'nullable|string',
            'digital_documents' => ['nullable', 'array'],
            'digital_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);
        $start = Carbon::parse($validated['start_datetime']);
        $end = Carbon::parse($validated['end_datetime']);
        $this->validateOfficeHours($start, $end);

        $user = Auth::user();
        $editId = $request->input('edit_id');
        $campus = $isUniversityWide ? 'All Campus' : $validated['campus'];
        $venue = trim($validated['venue_name']);

        // When editing, the request must not count as conflicting with itself
        $conflicts = $conflictChecker->find(
            $venue,
            $campus,
            $validated['start_datetime'],
            $validated['end_datetime'],
            $editId ? (int) $editId : null
        );

        if ($conflicts->isNotEmpty()) {
            return back()->withInput()->with(
                'error',
                'This venue is already booked during that time. Choose another venue or time before submitting.'
            );
        }

        $files = [];
        foreach ($request->file('digital_documents', []) as $file) {
            $files[] = $file->store('event-documents', 'public');
        }

        // ---- Editing an existing pending request ----
        if ($editId) {
            $existing = EventRequest::where('id', $editId)->where('email', $user->email)->first();

            if (! $existing) {
                return back()->withInput()->with('error', 'That request could not be found.');
            }

            if (! in_array($existing->status, EventRequest::EDITABLE_STATUSES, true)) {
                return back()->withInput()->with('error', 'Only pending requests can be edited.');
            }

            $existing->update([
                'title' => $request->input('title'),
                'venue_name' => $venue,
                'event_for' => $validated['event_for'] ?? null,
                'attention_to' => $validated['attention_to'] ?? null,
                'campus' => $campus,
                'is_university_wide' => $isUniversityWide,
                'description' => $request->input('description', ''),
                'start_datetime' => $request->input('start_datetime'),
                'end_datetime' => $request->input('end_datetime'),
                'status' => 'pending',
                'digital_documents' => $files ?: $existing->digital_documents,
                'read_at' => null,
            ]);

            ActivityLog::record($user->email, 'edited', "Edited request \"{$existing->title}\"");

            return redirect(route('office.request-status'))
                ->with('success', 'Your request has been updated.');
        }

        // ---- Creating a new request ----
        $new = EventRequest::create([
            'name' => $user->name,
            'email' => $user->email,
            'title' => $request->input('title'),
            'venue_name' => $venue,
            'event_for' => $validated['event_for'] ?? null,
            'attention_to' => $validated['attention_to'] ?? null,
            'campus' => $campus,
            'is_university_wide' => $isUniversityWide,
            'description' => $request->input('description', ''),
            'start_datetime' => $request->input('start_datetime'),
            'end_datetime' => $request->input('end_datetime'),
            'status' => 'pending',
            'digital_documents' => $files,
        ]);

        ActivityLog::record($user->email, 'submitted', "Submitted \"{$new->title}\" for {$new->venue_name}");

        return redirect(route('office.request-status'))
            ->with('success', 'Event request submitted. It is now awaiting Planning Office review.');
    }

    /**
     * Pre-fill the request form from a previous request (creates a new request on submit).
     */
    public function duplicateRequest($id)
    {
        $original = EventRequest::where('id', $id)->where('email', Auth::user()->email)->firstOrFail();

        return redirect(route('office.add-event'))->withInput([
            'title' => $original->title,
            'venue_name' => $original->venue_name,
            'event_for' => $original->event_for,
            'attention_to' => $original->attention_to,
            'campus' => $original->campus,
            'is_university_wide' => $original->is_university_wide ? '1' : '0',
            'description' => $original->description,
            'start_datetime' => optional($original->start_datetime)->format('Y-m-d\TH:i'),
            'end_datetime' => optional($original->end_datetime)->format('Y-m-d\TH:i'),
        ])->with('info', 'Form pre-filled from a previous request. Update the date and time, then submit.');
    }

    /**
     * Pre-fill the request form to edit an existing pending request (updates it on submit).
     */
    public function editRequestForm($id)
    {
        $original = EventRequest::where('id', $id)->where('email', Auth::user()->email)->firstOrFail();

        if (! in_array($original->status, EventRequest::EDITABLE_STATUSES, true)) {
            return redirect(route('office.request-status'))
                ->with('error', 'Only pending requests can be edited.');
        }

        return redirect(route('office.add-event'))->withInput([
            'edit_id' => $original->id,
            'title' => $original->title,
            'venue_name' => $original->venue_name,
            'event_for' => $original->event_for,
            'attention_to' => $original->attention_to,
            'campus' => $original->campus,
            'is_university_wide' => $original->is_university_wide ? '1' : '0',
            'description' => $original->description,
            'start_datetime' => optional($original->start_datetime)->format('Y-m-d\TH:i'),
            'end_datetime' => optional($original->end_datetime)->format('Y-m-d\TH:i'),
        ])->with('info', 'Editing request #' . $original->id . '. Update the details and submit to save changes.');
    }

    public function cancelRequest($id)
    {
        $user = Auth::user();
        $existing = EventRequest::where('id', $id)->where('email', $user->email)->firstOrFail();

        if (! in_array($existing->status, EventRequest::EDITABLE_STATUSES, true)) {
            return back()->with('error', 'Only pending requests can be cancelled.');
        }

        $existing->update(['status' => 'cancelled']);
        ActivityLog::record($user->email, 'cancelled', "Cancelled request \"{$existing->title}\"");

        return redirect(route('office.request-status'))
            ->with('success', 'Request cancelled.');
    }

    public function deleteCancelledRequest($id)
    {
        $user = Auth::user();
        $existing = EventRequest::where('id', $id)
            ->where('email', $user->email)
            ->where('status', 'cancelled')
            ->firstOrFail();

        ActivityLog::record($user->email, 'deleted', "Deleted cancelled request \"{$existing->title}\"");
        $existing->delete();

        return redirect(route('office.request-status'))
            ->with('success', 'Cancelled request deleted.');
    }

    public function toggleFavoriteVenue(Request $request)
    {
        $request->validate([
            'venue_name' => 'required|string|max:255',
            'campus' => 'nullable|string|max:255',
        ]);

        $email = Auth::user()->email;
        $existing = FavoriteVenue::where('email', $email)->where('venue_name', $request->input('venue_name'))->first();

        if ($existing) {
            $existing->delete();
            $message = 'Removed from favorite venues.';
        } else {
            FavoriteVenue::create([
                'email' => $email,
                'venue_name' => $request->input('venue_name'),
                'campus' => $request->input('campus'),
            ]);
            $message = 'Added to favorite venues.';
        }

        return back()->with('success', $message);
    }

    public function markNotificationsRead()
    {
        EventRequest::where('email', Auth::user()->email)
            ->whereIn('status', EventRequest::NOTIFIABLE_STATUSES)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'profile_password' => ['required'],
        ]);

        // Credentials change: confirm it's really the account owner
        if (! Hash::check($validated['profile_password'], $user->password)) {
            return back()->withInput()->withErrors([
                'profile_password' => 'Password is incorrect.',
            ])->with('open_profile_form', true);
        }

        unset($validated['profile_password']);
        $oldEmail = $user->email;

        DB::transaction(function () use ($user, $validated, $oldEmail) {
            $user->update($validated);

            // Requests, favorites and logs are keyed by email, so carry them over
            if ($validated['email'] !== $oldEmail) {
                EventRequest::where('email', $oldEmail)->update(['email' => $validated['email']]);
                FavoriteVenue::where('email', $oldEmail)->update(['email' => $validated['email']]);
                ActivityLog::where('email', $oldEmail)->update(['email' => $validated['email']]);
            }
        });

        ActivityLog::record($user->email, 'profile_updated', 'Updated office profile');

        return redirect()->route('office.dashboard')
            ->with('success', 'Office profile updated.');
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = Auth::user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Current password is incorrect.',
            ])->with('open_password_form', true);
        }

        $user->update(['password' => Hash::make($validated['new_password'])]);

        ActivityLog::record($user->email, 'password_changed', 'Changed account password');

        return redirect(route('office.dashboard') . '#office-profile')
            ->with('success', 'Password updated successfully.');
    }

    public function exportCsv(Request $request)
    {
        $email = Auth::user()->email;

        $query = EventRequest::where('email', $email);

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('q')) {
            $needle = $request->query('q');
            $query->where(function ($q) use ($needle) {
                $q->where('title', 'like', "%{$needle}%")->orWhere('venue_name', 'like', "%{$needle}%");
            });
        }
        if ($request->filled('from')) {
            $query->whereDate('start_datetime', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('start_datetime', '<=', $request->query('to'));
        }

        $rows = $query->orderBy('start_datetime', 'desc')->get();
        $filename = 'my-event-requests-' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Title', 'Venue', 'Campus', 'Start', 'End', 'Status', 'Planning Office Note']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->title,
                    $r->venue_name,
                    $r->campus,
                    optional($r->start_datetime)->format('Y-m-d H:i'),
                    optional($r->end_datetime)->format('Y-m-d H:i'),
                    ucfirst($r->status),
                    $r->planning_note,
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
