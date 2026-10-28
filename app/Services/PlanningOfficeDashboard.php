<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds everything the Planning Office dashboard shows, from real event requests.
 *
 * Filters:
 *  - office: one of the university offices, or null for all submitting offices.
 *  - from / to: events whose schedule overlaps the range.
 *
 * Requests are grouped by the office account that submitted them.
 */
class PlanningOfficeDashboard
{
    /** Event-related activity shown in "Recent activity" (not profile/password changes). */
    private const ACTIVITY_ACTIONS = ['submitted', 'edited', 'cancelled', 'approved', 'rejected', 'returned', 'rescheduled', 'conflict_resolved'];

    public function __construct(private EventConflictChecker $conflictChecker)
    {
    }

    public function build(?string $office, ?Carbon $from, ?Carbon $to): array
    {
        $now = now();
        [$academicYearStart, $academicYearEnd] = $this->academicYearRange($now);
        $from = $from?->copy()->startOfDay();
        $to = $to?->copy()->endOfDay();

        $inOffice = fn (EventRequest $e) => ! $office || $this->officeBucket($e) === $office;
        $inRange = fn (EventRequest $e) => (! $from || $e->end_datetime >= $from)
            && (! $to || $e->start_datetime <= $to);

        $officeEvents = EventRequest::orderBy('start_datetime')->get()
            ->filter(fn (EventRequest $e) => $inOffice($e)
                && $e->end_datetime >= $academicYearStart
                && $e->start_datetime <= $academicYearEnd)
            ->values();
        $overviewEvents = $officeEvents;
        $filtered = $officeEvents->filter($inRange)->values();
        $approved = $filtered->where('status', 'approved')->values();
        $overviewApproved = $overviewEvents->where('status', 'approved')->values();

        // ---- Review queue + conflicts ----
        $queue = $filtered->whereIn('status', EventRequest::REVIEW_STATUSES)
            ->sortBy([
                fn ($a, $b) => ($a->status === 'conflict' ? 0 : 1) <=> ($b->status === 'conflict' ? 0 : 1),
                fn ($a, $b) => $a->created_at <=> $b->created_at,
            ])
            ->values()
            ->each(function (EventRequest $e) {
                $e->setAttribute('conflicts', $this->conflictChecker->find(
                    $e->venue_name, $e->campus, $e->start_datetime, $e->end_datetime, $e->id
                ));
            });

        $overviewQueue = $overviewEvents->whereIn('status', EventRequest::REVIEW_STATUSES)
            ->values()
            ->each(function (EventRequest $e) {
                $e->setAttribute('conflicts', $this->conflictChecker->find(
                    $e->venue_name, $e->campus, $e->start_datetime, $e->end_datetime, $e->id
                ));
            });

        $conflicts = $queue->filter(fn ($e) => $e->status === 'conflict' || $e->conflicts->isNotEmpty())
            ->map(fn (EventRequest $e) => [
                'request' => $e,
                'with' => $e->conflicts,
                'type' => match (true) {
                    $e->conflicts->contains('status', 'approved') => 'Overlaps an approved event',
                    $e->conflicts->isNotEmpty() => 'Competing requests for the venue',
                    default => 'Flagged at submission (no longer overlapping)',
                },
            ])
            ->values();

        $conflictRequests = $conflicts->pluck('request');
        $overviewConflictRequests = $overviewQueue->filter(fn (EventRequest $e) => $e->status === 'conflict' || $e->conflicts->isNotEmpty());
        return [
            'summary' => $this->summary($overviewApproved, $overviewQueue, $overviewConflictRequests, $now),
            'academicYear' => [
                'label' => $academicYearStart->format('Y') . '-' . $academicYearEnd->format('Y'),
                'start' => $academicYearStart,
                'end' => $academicYearEnd,
            ],
            'officeSummaries' => collect($this->officeBuckets($overviewEvents))
                ->mapWithKeys(fn ($bucket) => [$bucket => $this->summary(
                    $overviewApproved->filter(fn ($e) => $this->officeBucket($e) === $bucket),
                    $overviewQueue->filter(fn ($e) => $this->officeBucket($e) === $bucket),
                    $overviewConflictRequests->filter(fn ($e) => $this->officeBucket($e) === $bucket),
                    $now,
                )]),
            'officeAccounts' => User::where('role', 'office')
                ->whereIn('email', array_keys(User::UNIVERSITY_OFFICE_ACCOUNT_LABELS))
                ->get()
                ->keyBy(fn (User $user) => User::UNIVERSITY_OFFICE_ACCOUNT_LABELS[$user->email]),
            'queue' => $queue,
            'conflicts' => $conflicts,
            'upcoming' => $approved->filter(fn ($e) => $e->start_datetime > $now)->take(7)->values(),
            // The calendar is navigated by month, so it ignores the date range (office filter still applies)
            'calendarEvents' => $this->calendarEvents($officeEvents),
            'charts' => $this->charts($filtered, $approved, $from, $to, $academicYearStart, $academicYearEnd),
            'activity' => ActivityLog::whereIn('action', self::ACTIVITY_ACTIONS)
                ->whereBetween('created_at', [$academicYearStart, $academicYearEnd])
                ->latest()
                ->limit(10)
                ->get(),
            'accountNames' => User::whereIn('role', ['office', 'planning_office'])->pluck('name', 'email'),
        ];
    }

    /** @return array{Carbon, Carbon} */
    public static function academicYearRange(?Carbon $now = null): array
    {
        $now ??= now();
        $start = $now->copy()->setDate($now->year, 8, 10)->startOfDay();
        if ($now->lt($start)) {
            $start->subYear();
        }

        return self::academicYearRangeForStartYear($start->year);
    }

    /** @return array{Carbon, Carbon} */
    public static function academicYearRangeForStartYear(int $startYear): array
    {
        $start = Carbon::create($startYear, 8, 10)->startOfDay();
        $end = Carbon::create($startYear + 1, 5, 28)->endOfDay();

        return [$start, $end];
    }

    /** The six overview numbers for one set of events. */
    private function summary(Collection $approved, Collection $queue, Collection $conflictRequests, Carbon $now): array
    {
        return [
            'total' => $approved->count(),
            'upcoming' => $approved->filter(fn ($e) => $e->start_datetime > $now)->count(),
            'ongoing' => $approved->filter(fn ($e) => $e->start_datetime <= $now && $e->end_datetime > $now)->count(),
            'completed' => $approved->filter(fn ($e) => $e->end_datetime <= $now)->count(),
            'pending' => $queue->count(),
            'conflicts' => $conflictRequests->count(),
        ];
    }

    /** Submitting office for an event, or "Other offices" when it is not one of the listed university offices. */
    private function officeBucket(EventRequest $e): string
    {
        return User::UNIVERSITY_OFFICE_ACCOUNT_LABELS[$e->email] ?? 'Other offices';
    }

    /** Listed university offices, plus "Other offices" only when a request falls outside those accounts. */
    private function officeBuckets(Collection $events): array
    {
        $buckets = array_values(User::UNIVERSITY_OFFICE_ACCOUNT_LABELS);
        if ($events->contains(fn ($e) => $this->officeBucket($e) === 'Other offices')) {
            $buckets[] = 'Other offices';
        }

        return $buckets;
    }

    public static function scopeLabel(string $scope): string
    {
        return $scope === EventRequest::SCOPE_UNIVERSITY_WIDE ? 'University-wide' : 'Campus-specific';
    }

    /** Campus column value: "All campuses" for university-wide events held for "All Campus". */
    public static function campusLabel(EventRequest $e): string
    {
        return $e->venueCampus() === 'All Campus' ? 'All campuses' : $e->venueCampus();
    }

    /**
     * Approved events only, coloured by scope. Submissions still under review (pending /
     * conflict) never go on the calendar; they're handled in the review queue.
     */
    private function calendarEvents(Collection $events): Collection
    {
        return $events->where('status', 'approved')
            ->map(function (EventRequest $e) {
                $universityWide = $e->scope() === EventRequest::SCOPE_UNIVERSITY_WIDE;
                [$kind, $color] = $universityWide ? ['university', '#047857'] : ['campus', '#4a5690'];

                return [
                    'title' => $e->title,
                    'start' => $e->start_datetime,
                    'end' => $e->end_datetime,
                    'description' => $e->description,
                    'venue' => $e->venue_name,
                    'campus' => $e->venueCampus(),
                    'university_wide' => $universityWide,
                    'office' => $e->name,
                    'status_label' => EventRequest::statusLabel($e->status),
                    'documents' => collect($e->digital_documents ?? [])
                        ->keys()
                        ->map(fn ($index) => [
                            'label' => 'Advisory Memo' . ($index > 0 ? ' ' . ($index + 1) : ''),
                            'url' => route('planning_office.events.documents.show', [
                                'event' => $e->id,
                                'documentIndex' => $index + 1,
                            ]),
                        ])
                        ->all(),
                    'color' => $color,
                    'class_names' => ['po-ev--' . $kind],
                ];
            })
            ->values();
    }

    private function charts(
        Collection $requests,
        Collection $approved,
        ?Carbon $from,
        ?Carbon $to,
        Carbon $academicYearStart,
        Carbon $academicYearEnd,
    ): array
    {
        $bucketOf = fn (EventRequest $e) => $this->officeBucket($e);
        $buckets = $this->officeBuckets($requests);

        // Events by submitting office (each event in exactly one bucket)
        $byOffice = collect($buckets)->mapWithKeys(fn ($b) => [$b => $approved->filter(fn ($e) => $bucketOf($e) === $b)->count()]);

        // Monthly trends: the selected range, or the 12 months around today
        $start = ($from ?? now()->subMonths(6))->copy()->startOfMonth()
            ->max($academicYearStart->copy()->startOfMonth());
        $end = ($to ?? now()->addMonths(5))->copy()->startOfMonth()
            ->min($academicYearEnd->copy()->startOfMonth());
        if ($start->lte($end) && $start->diffInMonths($end) > 23) {
            $start = $end->copy()->subMonths(23);
        }
        $months = [];
        for ($m = $start->copy(); $m <= $end; $m->addMonth()) {
            $months[$m->format('Y-m')] = $m->format('M Y');
        }
        $trendSeries = collect($buckets)->mapWithKeys(fn ($b) => [
            $b => collect(array_keys($months))->map(fn ($key) => $approved
                ->filter(fn ($e) => $bucketOf($e) === $b && $e->start_datetime->format('Y-m') === $key)
                ->count())->all(),
        ]);

        $scopeCounts = [
            EventRequest::SCOPE_UNIVERSITY_WIDE => $approved->filter(fn ($e) => $e->scope() === EventRequest::SCOPE_UNIVERSITY_WIDE)->count(),
            EventRequest::SCOPE_CAMPUS_SPECIFIC => $approved->filter(fn ($e) => $e->scope() === EventRequest::SCOPE_CAMPUS_SPECIFIC)->count(),
        ];

        return [
            'byOffice' => ['labels' => $byOffice->keys()->all(), 'values' => $byOffice->values()->all()],
            'trends' => ['labels' => array_values($months), 'series' => $trendSeries->all()],
            'scope' => ['labels' => ['University-wide', 'Campus-specific'], 'values' => array_values($scopeCounts)],
        ];
    }
}
