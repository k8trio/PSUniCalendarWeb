<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EventRequest;
use App\Models\User;
use App\Models\Venue;
use App\Services\EventConflictChecker;
use App\Services\PlanningOfficeDashboard;
use App\Services\ReportTemplateFonts;
use App\Services\GoogleCalendarEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;

class PlanningOfficeController extends Controller
{
    public function addEvent()
    {
        $venueOptions = Venue::orderBy('name')->pluck('name')
            ->merge(EventRequest::distinct()->pluck('venue_name'))
            ->filter()
            ->unique(fn ($name) => mb_strtolower(trim($name)))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $offices = User::where('role', 'office')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('superadmin.add-event', compact('venueOptions', 'offices'));
    }

    public function storeEvent(
        Request $request,
        EventConflictChecker $conflictChecker,
        GoogleCalendarEventService $calendar
    ) {
        $isUniversityWide = $request->boolean('is_university_wide');
        foreach ($request->file('digital_documents', []) as $index => $file) {
            if (! $file instanceof \Illuminate\Http\UploadedFile || $file->isValid()) {
                continue;
            }

            $uploadError = $file->getError();
            $message = match ($uploadError) {
                UPLOAD_ERR_INI_SIZE => 'The document exceeds the server upload limit of ' . ini_get('upload_max_filesize') . '. The application limit is 10 MB per file.',
                UPLOAD_ERR_FORM_SIZE => 'The document exceeds the maximum size allowed by the form.',
                UPLOAD_ERR_PARTIAL => 'The document upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server upload temporary folder is unavailable. Please contact support.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded document. Please contact support.',
                UPLOAD_ERR_EXTENSION => 'A server extension stopped the document upload. Please contact support.',
                default => 'The document upload failed (PHP upload error ' . $uploadError . '). Please try again or contact support.',
            };

            Log::warning('Planning Office supporting document upload failed.', [
                'user_id' => Auth::id(),
                'upload_error_code' => $uploadError,
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ]);

            throw \Illuminate\Validation\ValidationException::withMessages([
                "digital_documents.{$index}" => $message,
            ]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
             'office_advisory_no' => ['nullable', 'string', 'max:255'],
            'series_text' => ['nullable', 'string', 'max:255'],
            'venue_name' => ['required', 'string', 'max:255'],
            'event_for' => ['nullable', 'string', 'max:5000'],
            'attention_to' => ['nullable', 'string', 'max:5000'],
            'campus' => $isUniversityWide
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', Rule::in(User::CAMPUSES)],
            'is_university_wide' => ['nullable', 'boolean'],
            'notify_offices' => ['nullable', 'array'],
            'notify_offices.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'office')),
            ],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'description' => ['nullable', 'string'],
            'digital_documents' => ['nullable', 'array'],
            'digital_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ], [
            'digital_documents.*.uploaded' => 'The supporting document could not be uploaded. Use a PDF, JPG, JPEG, PNG, DOC, or DOCX file no larger than 10 MB, then try again.',
        ]);

        $start = Carbon::parse($validated['start_datetime']);
        $end = Carbon::parse($validated['end_datetime']);
        if (! EventConflictChecker::isWithinOfficeHours($start, $end)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_datetime' => 'Events must start and end between 8:00 AM and 5:00 PM. Multi-day events are allowed within these hours.',
            ]);
        }

        $campus = $isUniversityWide ? 'All Campus' : $validated['campus'];
        $venue = trim($validated['venue_name']);
        $conflicts = $conflictChecker->find($venue, $campus, $start, $end);
        if ($conflicts->isNotEmpty()) {
            return back()->withInput()->with(
                'error',
                'This venue is already booked during that time. Choose another venue or time before adding the event.'
            );
        }

        $files = [];
        foreach ($request->file('digital_documents', []) as $file) {
            $files[] = $file->store('event-documents', 'public');
        }

        $planningOffice = Auth::user();
        $googleEventId = null;
        if ($calendar->isConfigured()) {
            try {
                $pending = new EventRequest([
                    'name' => $planningOffice->name,
                    'email' => $planningOffice->email,
                    'office_advisory_no' => $validated['office_advisory_no'] ?? null,
                    'series_text' => $validated['series_text'] ?? null,
                    'title' => $validated['title'],
                    'venue_name' => $venue,
                    'event_for' => $validated['event_for'] ?? null,
                    'attention_to' => $validated['attention_to'] ?? null,
                    'campus' => $campus,
                    'is_university_wide' => $isUniversityWide,
                    'description' => $validated['description'] ?? null,
                    'start_datetime' => $start,
                    'end_datetime' => $end,
                    'status' => 'approved',
                    'digital_documents' => $files,
                ]);
                $googleEventId = $calendar->insert($pending);
            } catch (\Google\Exception|\InvalidArgumentException $exception) {
                \Illuminate\Support\Facades\Log::error('Google Calendar insert failed while adding a Planning Office event.', [
                    'message' => $exception->getMessage(),
                ]);
                return back()->withInput()->with('error', 'The event could not be synced to Google Calendar. Nothing was added.');
            }
        }

        $recipientIds = $validated['notify_offices'] ?? [];

        DB::transaction(function () use ($planningOffice, $validated, $venue, $campus, $isUniversityWide, $start, $end, $files, $googleEventId, $recipientIds): void {
            $event = EventRequest::create([
                'name' => $planningOffice->name,
                'email' => $planningOffice->email,
                'office_advisory_no' => $validated['office_advisory_no'] ?? null,
                'series_text' => $validated['series_text'] ?? null,
                'title' => $validated['title'],
                'venue_name' => $venue,
                'event_for' => $validated['event_for'] ?? null,
                'attention_to' => $validated['attention_to'] ?? null,
                'campus' => $campus,
                'is_university_wide' => $isUniversityWide,
                'description' => $validated['description'] ?? null,
                'start_datetime' => $start,
                'end_datetime' => $end,
                'status' => 'approved',
                'digital_documents' => $files,
                'google_event_id' => $googleEventId,
            ]);
            $event->recipients()->sync($recipientIds);

            ActivityLog::record($planningOffice->email, 'approved', "Planning Office added \"{$event->title}\"");
        });

        return redirect()->route('planning_office.add-event')
            ->with('event_added', $validated['title']);
    }

    public function dashboard(Request $request, PlanningOfficeDashboard $dashboard)
    {
        $filters = $request->validate([
            'office' => ['nullable', Rule::in(array_values(User::PLANNING_OFFICE_ACCOUNT_LABELS))],
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

    public function detailedReport(Request $request)
    {
        return view('superadmin.reports.events', $this->detailedReportData($request));
    }

    public function downloadDetailedReport(Request $request)
    {
        $reportData = $this->detailedReportData($request);
        $templateFonts = app(ReportTemplateFonts::class)->forPdf();
        $reportData['templateFonts'] = $templateFonts;

        return Pdf::loadView('reports.event-requests', $reportData)
            ->setOption('chroot', $templateFonts['chroot'])
            ->setOption('enable_font_subsetting', true)
            ->setPaper([0, 0, 612, 936])
            ->download('planning-office-event-report-' . now()->format('Ymd-His') . '.pdf');
    }

    public function planningOfficeAddedEventsReport()
    {
        $events = $this->planningOfficeAddedEventsQuery()->get();

        return view('superadmin.reports.planning-office-events', compact('events'));
    }

    public function downloadPlanningOfficeAddedEventsReport()
    {
        $templateFonts = app(ReportTemplateFonts::class)->forPdf();

        return Pdf::loadView('reports.planning-office-events', [
            'events' => $this->planningOfficeAddedEventsQuery()->get(),
            'reportTitle' => 'Planning Office Added Events Detailed Report',
            'templateFonts' => $templateFonts,
        ])
            ->setOption('chroot', $templateFonts['chroot'])
            ->setOption('enable_font_subsetting', true)
            ->setPaper([0, 0, 612, 936])
            ->download('planning-office-added-events-' . now()->format('Ymd-His') . '.pdf');
    }

    private function planningOfficeAddedEventsQuery()
    {
        $planningOfficeEmails = User::query()
            ->where('role', 'planning_office')
            ->pluck('email');

        return EventRequest::query()
            ->whereIn('email', $planningOfficeEmails)
            ->orderByDesc('start_datetime');
    }

    private function detailedReportData(Request $request): array
    {
        $allOfficeAccounts = User::where('role', 'office')->orderBy('name')->get(['id', 'name', 'email', 'campus']);
        $reportOffices = $allOfficeAccounts
            ->whereNotIn('campus', User::MAIN_CAMPUSES)
            ->concat(
                $allOfficeAccounts
                    ->whereIn('campus', User::MAIN_CAMPUSES)
                    ->sortBy(fn (User $office) => array_search($office->campus, User::MAIN_CAMPUSES, true))
            )
            ->values();
        $validOfficeIds = $reportOffices->pluck('id')->map(fn ($id) => (string) $id)->all();
        $filters = $request->validate([
            'all_offices' => ['nullable', 'boolean'],
            'filter_applied' => ['nullable', 'boolean'],
            'offices' => ['nullable', 'array'],
            'offices.*' => ['required', Rule::in($validOfficeIds)],
            'office' => ['nullable', Rule::in($validOfficeIds)],
        ]);

        $selectedOfficeIds = $filters['offices'] ?? [];
        if ($selectedOfficeIds === [] && ! empty($filters['office'])) {
            $selectedOfficeIds = [(string) $filters['office']];
        }

        $allOffices = array_key_exists('all_offices', $filters)
            ? (bool) $filters['all_offices']
            : (! $request->boolean('filter_applied') && $selectedOfficeIds === []);

        $selectedOffices = $reportOffices
            ->whereIn('id', array_map('intval', $selectedOfficeIds))
            ->values();
        $query = EventRequest::query();
        if (! $allOffices) {
            $query->whereIn('email', $selectedOffices->pluck('email'));
        }

        $events = $query
            ->orderBy('email')
            ->orderByDesc('start_datetime')
            ->get();
        $officeNames = $allOfficeAccounts->pluck('name', 'email');
        $events->each(function (EventRequest $event) use ($officeNames) {
            $event->setAttribute(
                'report_office_name',
                ($officeNames[$event->email] ?? null) ?: ($event->name ?: 'Unknown office')
            );
        });

        $reportFilters = [
            'all_offices' => $allOffices,
            'offices' => $allOffices ? [] : $selectedOffices->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ];
        if ($allOffices) {
            $selectedOfficeName = 'All offices';
        } elseif ($selectedOffices->isEmpty()) {
            $selectedOfficeName = 'No offices selected';
        } elseif ($selectedOffices->count() === 1) {
            $selectedOfficeName = $selectedOffices->first()->name;
        } else {
            $selectedOfficeName = $selectedOffices->count() . ' offices selected';
        }

        return [
            'reportTitle' => 'Office Event Detailed Report',
            'officeName' => null,
            'events' => $events,
            'includeOfficeColumn' => true,
            'showPostEndDetails' => false,
            'advisoryDocumentRouteName' => 'planning_office.events.documents.show',
            'reportOffices' => $reportOffices,
            'reportFilters' => $reportFilters,
            'reportFilterLabel' => $selectedOfficeName,
            'filterError' => ! $allOffices && $selectedOffices->isEmpty()
                ? 'Select one or more offices, or click Select all.'
                : null,
        ];
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
        $officeBreakdown = collect(array_fill_keys(array_values(User::PLANNING_OFFICE_ACCOUNT_LABELS), 0))
            ->merge($approvedEvents->countBy(
                fn (EventRequest $event) => User::PLANNING_OFFICE_ACCOUNT_LABELS[$event->email] ?? 'Other offices'
            ));
        $monthlyEvents = [];
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
            'offices' => $officeBreakdown,
            'monthly' => $monthlyEvents,
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

                foreach (User::PLANNING_OFFICE_ACCOUNT_LABELS as $email => $label) {
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

    public function resetOfficePassword(User $office)
    {
        abort_unless($office->role === 'office', 404);

        $temporaryPassword = 'Office@123';
        $planningOffice = Auth::user();

        DB::transaction(function () use ($office, $planningOffice, $temporaryPassword): void {
            $office->update([
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ]);

            ActivityLog::record(
                $office->email,
                'password_reset',
                "Password reset by Planning Office account \"{$planningOffice->name}\". A temporary password was issued."
            );
        });

        return redirect()->route('planning_office.offices.show', $office)
            ->with('temporary_password', $temporaryPassword);
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