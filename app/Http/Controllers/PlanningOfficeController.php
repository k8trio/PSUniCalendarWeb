<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EventRequest;
use App\Models\User;
use App\Models\Venue;
use App\Services\EventConflictChecker;
use App\Services\PlanningOfficeDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PlanningOfficeController extends Controller
{
    public function dashboard(Request $request, PlanningOfficeDashboard $dashboard)
    {
        $filters = $request->validate([
            'office' => ['nullable', Rule::in(array_values(User::UNIVERSITY_OFFICE_ACCOUNT_LABELS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $data = $dashboard->build(
            $filters['office'] ?? null,
            isset($filters['from']) ? Carbon::parse($filters['from']) : null,
            isset($filters['to']) ? Carbon::parse($filters['to']) : null,
        );

        return view('superadmin.dashboard', $data + ['filters' => array_filter($filters)]);
    }

    public function archive(Request $request)
    {
        $currentAcademicYear = PlanningOfficeDashboard::academicYearRange()[0];
        $academicYears = collect(range(1, 3))
            ->map(function (int $yearsAgo) use ($currentAcademicYear) {
                [$start, $end] = PlanningOfficeDashboard::academicYearRangeForStartYear($currentAcademicYear->year - $yearsAgo);

                return [
                    'label' => $start->format('Y') . '-' . $end->format('Y'),
                    'start' => $start,
                    'end' => $end,
                ];
            });

        $validated = $request->validate([
            'year' => ['nullable', Rule::in($academicYears->pluck('label')->all())],
        ]);
        $selectedYear = $academicYears->firstWhere('label', $validated['year'] ?? null) ?? $academicYears->first();

        $archiveQuery = EventRequest::query()
            ->where('status', 'approved')
            ->where('end_datetime', '>=', $selectedYear['start'])
            ->where('start_datetime', '<=', $selectedYear['end']);

        $approvedEvents = (clone $archiveQuery)->get();
        $campusBreakdown = $approvedEvents
            ->groupBy(fn (EventRequest $event) => PlanningOfficeDashboard::campusLabel($event))
            ->map(fn ($events) => $events->count())
            ->sortDesc();
        $monthlyEvents = [];
        $resolvedConflicts = ActivityLog::query()
            ->where('action', 'conflict_resolved')
            ->whereBetween('created_at', [$selectedYear['start'], $selectedYear['end']])
            ->count();
        for ($month = $selectedYear['start']->copy()->startOfMonth(); $month <= $selectedYear['end']; $month->addMonth()) {
            $monthKey = $month->format('Y-m');
            $monthLabel = $month->format('M Y');
            $monthlyEvents[$monthLabel] = $approvedEvents
                ->filter(fn (EventRequest $event) => $event->start_datetime->format('Y-m') === $monthKey)
                ->count();
        }
        $analytics = [
            'total' => $approvedEvents->count(),
            'universityWide' => $approvedEvents->filter(fn (EventRequest $event) => $event->scope() === EventRequest::SCOPE_UNIVERSITY_WIDE)->count(),
            'campusSpecific' => $approvedEvents->filter(fn (EventRequest $event) => $event->scope() === EventRequest::SCOPE_CAMPUS_SPECIFIC)->count(),
            'campuses' => $campusBreakdown,
            'monthly' => $monthlyEvents,
            'resolvedConflicts' => $resolvedConflicts,
        ];

        $events = (clone $archiveQuery)
            ->orderBy('start_datetime')
            ->paginate(25)
            ->withQueryString();

        return view('superadmin.archive', compact('academicYears', 'selectedYear', 'events', 'analytics'));
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        $query = trim($validated['q']);
        $like = '%' . $query . '%';

        $offices = User::query()
            ->where('role', 'office')
            ->where(function ($users) use ($like, $query) {
                $users->where('name', 'like', $like)
                    ->orWhere('campus', 'like', $like)
                    ->orWhere('email', 'like', $like);

                foreach (User::UNIVERSITY_OFFICE_ACCOUNT_LABELS as $email => $label) {
                    if (str_contains(mb_strtolower($label), mb_strtolower($query))) {
                        $users->orWhere('email', $email);
                    }
                }
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        $officeEmails = $offices->pluck('email')->all();
        $events = EventRequest::query()
            ->where(function ($requests) use ($like, $officeEmails) {
                $requests->where('title', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('venue_name', 'like', $like)
                    ->orWhere('campus', 'like', $like)
                    ->orWhere('event_for', 'like', $like)
                    ->orWhere('attention_to', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('email', 'like', $like);

                if ($officeEmails !== []) {
                    $requests->orWhereIn('email', $officeEmails);
                }
            })
            ->orderByDesc('start_datetime')
            ->paginate(20)
            ->withQueryString();

        $officeNames = User::whereIn('role', ['office', 'planning_office'])->pluck('name', 'email');

        return view('superadmin.search', compact('query', 'offices', 'events', 'officeNames'));
    }

    public function showEvent(EventRequest $event)
    {
        return view('superadmin.event', compact('event'));
    }

    public function eventDocument(EventRequest $event, int $documentIndex)
    {
        $documents = $event->digital_documents ?? [];
        abort_unless($documentIndex >= 1 && isset($documents[$documentIndex - 1]), 404);

        $path = $documents[$documentIndex - 1];
        abort_unless(
            is_string($path)
                && str_starts_with($path, 'event-documents/')
                && basename($path) === substr($path, strlen('event-documents/')),
            404
        );
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, basename($path), [], 'inline');
    }

    public function showOffice(User $office)
    {
        abort_unless($office->role === 'office', 404);

        [$academicYearStart, $academicYearEnd] = PlanningOfficeDashboard::academicYearRange();
        $requests = EventRequest::where('email', $office->email)
            ->orderByDesc('created_at')
            ->get();
        $approvedAcademicYearEvents = EventRequest::where('email', $office->email)
            ->where('status', 'approved')
            ->where('end_datetime', '>=', $academicYearStart)
            ->where('start_datetime', '<=', $academicYearEnd)
            ->orderBy('start_datetime')
            ->get();
        $academicYear = [
            'label' => $academicYearStart->format('Y') . '-' . $academicYearEnd->format('Y'),
            'start' => $academicYearStart,
            'end' => $academicYearEnd,
        ];

        $statusCounts = [
            'pending' => $requests->whereIn('status', ['pending', 'conflict'])->count(),
            'approved' => $requests->where('status', 'approved')->count(),
            'rejected' => $requests->where('status', 'rejected')->count(),
            'cancelled' => $requests->where('status', 'cancelled')->count(),
        ];

        $recentActivity = ActivityLog::where('email', $office->email)->latest()->limit(10)->get();

        return view('superadmin.office', compact(
            'office',
            'requests',
            'statusCounts',
            'recentActivity',
            'approvedAcademicYearEvents',
            'academicYear',
        ));
    }

    public function updateOfficeCampus(Request $request, User $office)
    {
        abort_unless($office->role === 'office', 404);

        $validated = $request->validate([
            'campus' => ['required', Rule::in(User::OFFICE_GROUPS)],
        ]);

        $office->update(['campus' => $validated['campus']]);

        return redirect()->route('planning_office.offices.show', $office)
            ->with('success', "{$office->name} is now listed under {$validated['campus']}.");
    }

    public function notifications()
    {
        $needsReview = EventRequest::whereIn('status', ['pending', 'conflict'])
            ->orderByRaw("CASE WHEN status = 'conflict' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->get();

        $upcomingSoon = EventRequest::where('status', 'approved')
            ->whereBetween('start_datetime', [now(), now()->addDays(7)])
            ->orderBy('start_datetime')
            ->get();

        $recentActivity = ActivityLog::latest()->limit(20)->get();
        $officeNames = User::whereIn('role', ['office', 'planning_office'])->pluck('name', 'email');

        return view('superadmin.notifications', compact('needsReview', 'upcomingSoon', 'recentActivity', 'officeNames'));
    }

    public function settings()
    {
        return view('superadmin.settings', ['user' => Auth::user()]);
    }

    public function updateSettingsProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withInput()->withErrors(['current_password' => 'Password is incorrect.'], 'profile');
        }

        $user->update(['name' => $validated['name'], 'email' => $validated['email']]);

        return redirect()->route('planning_office.settings')->with('success', 'Account details updated.');
    }

    public function updateSettingsPassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validateWithBag('password', [
            'current_password' => ['required'],
            'new_password' => ['required', 'confirmed', 'min:8'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'], 'password');
        }

        $user->update(['password' => Hash::make($validated['new_password'])]);

        return redirect()->route('planning_office.settings')->with('success', 'Password updated successfully.');
    }

    public function pendingApprovals(EventConflictChecker $conflictChecker)
    {
        $requests = EventRequest::whereIn('status', ['pending', 'conflict'])
            ->orderByRaw("CASE WHEN status = 'conflict' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->get()
            ->each(function (EventRequest $request) use ($conflictChecker) {
                $request->setAttribute('conflicts', $conflictChecker->find(
                    $request->venue_name,
                    $request->campus,
                    $request->start_datetime,
                    $request->end_datetime,
                    $request->id
                ));
            });

        return view('office.pending-approvals', compact('requests'));
    }

    public function manageVenues()
    {
        $venues = Venue::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(function ($venue) {
                $venue->venue_name = $venue->name;
                $venue->events_count = EventRequest::where('status', 'approved')
                    ->where('venue_name', $venue->name)
                    ->count();

                return $venue;
            });

        if ($venues->isEmpty()) {
            $venues = collect([
                ['id' => 1, 'name' => 'Main Auditorium', 'venue_name' => 'Main Auditorium', 'events_count' => 2],
                ['id' => 2, 'name' => 'Science Hall', 'venue_name' => 'Science Hall', 'events_count' => 1],
                ['id' => 3, 'name' => 'Student Center', 'venue_name' => 'Student Center', 'events_count' => 3],
                ['id' => 4, 'name' => 'Sports Gym', 'venue_name' => 'Sports Gym', 'events_count' => 1],
            ])->map(fn ($venue) => (object) $venue);
        }

        return view('superadmin.manage-venues', compact('venues'));
    }

    public function storeVenue(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:venues,name'],
        ]);

        Venue::create(['name' => trim($validated['name'])]);

        return redirect()->route('planning_office.venues')->with('success', 'Venue added successfully.');
    }

    public function updateVenue(Request $request, Venue $venue)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:venues,name,' . $venue->id],
        ]);

        $venue->update(['name' => trim($validated['name'])]);

        return redirect()->route('planning_office.venues')->with('success', 'Venue updated successfully.');
    }

    public function destroyVenue(Venue $venue)
    {
        $venue->delete();

        return redirect()->route('planning_office.venues')->with('success', 'Venue removed successfully.');
    }

    public function venueEvents(string $venue)
    {
        $events = EventRequest::where('status', 'approved')
            ->where('venue_name', $venue)
            ->orderBy('start_datetime', 'asc')
            ->get();

        return view('superadmin.venue-events', compact('events', 'venue'));
    }
}