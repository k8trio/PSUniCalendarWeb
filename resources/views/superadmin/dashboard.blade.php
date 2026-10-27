@extends('layouts.planning-office')

@section('title', 'Dashboard')
@section('heading', 'Planning Office Dashboard')
@section('subheading')
    Welcome, {{ auth()->user()->name }} · {{ now()->format('l, F j, Y') }}
@endsection

@push('head')
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <link href="{{ asset('css/event-calendar.css') }}" rel="stylesheet">
@endpush

@php
    $dash = \App\Services\PlanningOfficeDashboard::class;
    $statusLabel = fn ($status) => \App\Models\EventRequest::statusLabel($status);
    $selectedOffice = $filters['office'] ?? '';
    $activityLabels = [
        'submitted' => 'Submitted', 'edited' => 'Edited', 'cancelled' => 'Cancelled', 'approved' => 'Approved',
        'rejected' => 'Rejected', 'returned' => 'Returned', 'rescheduled' => 'Rescheduled', 'conflict_resolved' => 'Conflict resolved',
    ];
    $activityBadge = [
        'submitted' => 'pending', 'edited' => 'pending', 'cancelled' => 'cancelled', 'approved' => 'approved',
        'rejected' => 'rejected', 'returned' => 'returned', 'rescheduled' => 'approved', 'conflict_resolved' => 'approved',
    ];
    $cards = [
        ['Total events', 'total', '', 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
        ['Upcoming events', 'upcoming', '', 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['Ongoing events', 'ongoing', 'approved', 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z'],
        ['Pending approvals', 'pending', 'pending', 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z'],
        ['Completed events', 'completed', 'cancelled', 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['Scheduling conflicts', 'conflicts', 'rejected', 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z'],
    ];
@endphp

@section('content')
    @if($errors->any())
        <div class="od-alert od-alert--error">{{ $errors->first() }}</div>
    @endif

    <div class="po-dashboard-top">
        {{-- ===== Overview: all offices; click to see the same numbers per office ===== --}}
        <details class="od-card po-overview" id="overview">
        <summary class="po-overview__summary">
            <span class="po-overview__head">
                <span>
                    <span class="od-section-label" style="margin:0">University Event Overview{{ $selectedOffice ? ' · ' . $selectedOffice : ' · All Offices' }}</span>
                    <span class="po-overview__academic-year">Academic Year {{ $academicYear['label'] }} · {{ $academicYear['start']->format('M j, Y') }} – {{ $academicYear['end']->format('M j, Y') }}</span>
                </span>
                <span class="po-overview__toggle" aria-hidden="true">
                    <span class="po-overview__toggle-show">Show by office</span>
                    <span class="po-overview__toggle-hide">Hide offices</span>
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                </span>
            </span>
            <span class="po-overview__stats-scroll" tabindex="0" role="region" aria-label="Academic year overview metrics">
                <span class="od-stats po-summary">
                    @foreach($cards as [$label, $key, $modifier, $icon])
                        <span class="od-stat po-stat {{ $modifier ? 'od-stat--' . $modifier : '' }}">
                            <span class="po-stat__icon" aria-hidden="true">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                            </span>
                            <span>
                                <span class="od-stat__label">{{ $label }}</span>
                                <span class="od-stat__value">{{ $summary[$key] }}</span>
                            </span>
                        </span>
                    @endforeach
                </span>
            </span>
        </summary>

        <div class="po-overview__offices">
            <p class="od-card__sub">Requests are grouped by the university office that submitted them.</p>
            <div class="od-table-wrap">
                <table class="od-table po-overview__table">
                    <thead>
                        <tr>
                            <th scope="col">Office</th>
                            @foreach($cards as [$label])
                                <th scope="col">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($officeSummaries as $officeName => $officeSummary)
                            <tr>
                                <th scope="row" class="od-table__title">{{ $officeName }}</th>
                                @foreach($cards as [$label, $key, $modifier])
                                    <td class="{{ $officeSummary[$key] && in_array($modifier, ['approved', 'pending', 'rejected']) ? 'od-text-' . $modifier : '' }}">{{ $officeSummary[$key] }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        </details>

        {{-- ===== Analytics ===== --}}
        <section class="od-card po-analytics" aria-labelledby="analytics-title">
            <div class="od-card__head">
                <div>
                    <h2 id="analytics-title" class="od-card__title">Analytics</h2>
                    <p class="od-card__sub">Approved events grouped by submitting office{{ $selectedOffice ? ' (' . $selectedOffice . ')' : '' }}{{ ($filters['from'] ?? null) || ($filters['to'] ?? null) ? ' in the selected date range' : '' }}.</p>
                </div>
            </div>
            <div class="po-charts">
                <figure class="po-chart-card po-chart-card--office">
                    <figcaption>Events by office</figcaption>
                    <div class="po-chart po-chart--office"><canvas id="chart-by-office" aria-label="Events by office"></canvas></div>
                </figure>
                <figure class="po-chart-card">
                    <figcaption>Office event trends <span>· monthly</span></figcaption>
                    <div class="po-chart"><canvas id="chart-trends" aria-label="Monthly office event trends"></canvas></div>
                </figure>
                <figure class="po-chart-card">
                    <figcaption>Scheduling conflict overview <span>· {{ $summary['conflicts'] }} open · {{ $resolvedConflicts }} resolved{{ ($filters['from'] ?? null) || ($filters['to'] ?? null) ? ' in range' : '' }}</span></figcaption>
                    <div class="po-chart"><canvas id="chart-conflicts" aria-label="Open conflicts by office"></canvas></div>
                </figure>
            </div>
        </section>
    </div>

    {{-- ===== Pending approvals ===== --}}
    <section id="pending-approvals" class="od-card">
        <div class="od-card__head">
            <div>
                <h2 class="od-card__title">Pending Approvals</h2>
            </div>
            <a href="{{ route('planning_office.pending') }}" class="od-link">Open full review →</a>
        </div>

        <div class="po-table-filters" id="pending-filters">
            <input type="search" data-filter="q" placeholder="Search title, organizer or venue..." class="od-input od-input--sm" aria-label="Search pending approvals">
            <select data-filter="status" class="od-input od-input--sm" aria-label="Status">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="conflict">Conflict review</option>
            </select>
            <select data-filter="scope" class="od-input od-input--sm" aria-label="Scope">
                <option value="">All scopes</option>
                <option value="{{ \App\Models\EventRequest::SCOPE_CAMPUS_SPECIFIC }}">Campus-specific</option>
                <option value="{{ \App\Models\EventRequest::SCOPE_UNIVERSITY_WIDE }}">University-wide</option>
            </select>
        </div>

        <div class="od-table-wrap">
            <table class="od-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Campus</th>
                        <th>Scope</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th>Organizer</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($queue as $item)
                        <tr data-row
                            data-search="{{ mb_strtolower($item->title . ' ' . $item->name . ' ' . $item->venue_name) }}"
                            data-status="{{ $item->status }}"
                            data-scope="{{ $item->scope() }}">
                            <td><span class="od-table__title">{{ $item->title }}</span><span class="po-sub">{{ $item->venue_name }}</span></td>
                            <td>{{ $dash::campusLabel($item) }}</td>
                            <td><span class="po-scope po-scope--{{ strtolower($item->scope()) }}">{{ $dash::scopeLabel($item->scope()) }}</span></td>
                            <td>{{ $item->sdg_number ? 'SDG ' . $item->sdg_number : '—' }}</td>
                            <td>{{ $item->start_datetime->format('M j, Y') }}<span class="po-sub">{{ $item->start_datetime->format('g:i A') }} – {{ $item->end_datetime->format('g:i A') }}</span></td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->created_at?->format('M j, Y') }}</td>
                            <td><span class="od-badge od-badge--{{ $item->status }}">{{ $statusLabel($item->status) }}</span></td>
                            <td>
                                <div class="od-table__actions po-actions">
                                    <a href="{{ route('planning_office.pending') }}#request-{{ $item->id }}" class="od-link">View</a>
                                    <form method="POST" action="{{ route('planning_office.approve', $item->id) }}" onsubmit="return confirm('Approve this request as submitted?');">
                                        @csrf
                                        @if($item->conflicts->isNotEmpty())
                                            <button type="submit" class="od-link" disabled title="Resolve the schedule conflict first: open View to adjust the venue or time">Approve</button>
                                        @else
                                            <button type="submit" class="od-link">Approve</button>
                                        @endif
                                    </form>
                                    <button type="button" class="od-link" data-decision="return" data-action="{{ route('planning_office.return', $item->id) }}" data-title="{{ $item->title }}">Return</button>
                                    <button type="button" class="od-link od-link--danger" data-decision="reject" data-action="{{ route('planning_office.reject', $item->id) }}" data-title="{{ $item->title }}">Reject</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    <tr data-empty class="{{ $queue->isEmpty() ? '' : 'hidden' }}">
                        <td colspan="9" class="od-table__empty">{{ $queue->isEmpty() ? 'No submissions are awaiting review.' : 'No submissions match your search.' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- ===== Scheduling conflicts ===== --}}
    <section id="conflicts" class="od-card">
        <div class="od-card__head">
            <div>
                <h2 class="od-card__title">Scheduling Conflicts</h2>
                <p class="od-card__sub">Resolve by adjusting the venue or time and approving, or by rejecting — the planning note is kept as the resolution.</p>
            </div>
            <span class="od-badge od-badge--rejected">{{ $conflicts->count() }} open</span>
        </div>
        <div class="od-table-wrap">
            <table class="od-table">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Conflicts with</th>
                        <th>Campus</th>
                        <th>Venue</th>
                        <th>Date &amp; time</th>
                        <th>Conflict type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($conflicts as $row)
                        @php($req = $row['request'])
                        <tr>
                            <td><span class="od-table__title">{{ $req->title }}</span><span class="po-sub">{{ $req->name }}</span></td>
                            <td>
                                @forelse($row['with'] as $other)
                                    <span class="po-sub" style="color:inherit">{{ $other->title }} <span class="od-muted">({{ $statusLabel($other->status) }})</span></span>
                                @empty
                                    <span class="od-muted">—</span>
                                @endforelse
                            </td>
                            <td>{{ $dash::campusLabel($req) }}</td>
                            <td>{{ $req->venue_name }}</td>
                            <td>{{ $req->start_datetime->format('M j, Y') }}<span class="po-sub">{{ $req->start_datetime->format('g:i A') }} – {{ $req->end_datetime->format('g:i A') }}</span></td>
                            <td>{{ $row['type'] }}</td>
                            <td><span class="od-badge od-badge--{{ $req->status }}">{{ $statusLabel($req->status) }}</span></td>
                            <td><a href="{{ route('planning_office.pending') }}#request-{{ $req->id }}" class="od-link">Review &amp; resolve</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="od-table__empty">No scheduling conflicts{{ $filters ? ' for these filters' : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ===== Centralized calendar ===== --}}
    <section id="calendar" class="od-card">
        <div class="od-card__head">
            <div>
                <h2 class="od-card__title">Master Live Calendar</h2>
            </div>
        </div>
        <div id="planning-office-calendar" class="od-calendar"></div>
    </section>

    <div class="od-two-col">
        {{-- ===== Upcoming events ===== --}}
        <section class="od-card">
            <div class="od-card__head">
                <h2 class="od-card__title">Upcoming Events</h2>
                <span class="od-pill">Next {{ $upcoming->count() }}</span>
            </div>
            @if($upcoming->isEmpty())
                <p class="od-empty">No upcoming approved events{{ $filters ? ' for these filters' : '' }}.</p>
            @else
                <ul class="po-list">
                    @foreach($upcoming as $event)
                        <li class="po-list__item">
                            <div>
                                <p class="po-list__title">{{ $event->title }}</p>
                                <p class="po-list__meta">{{ $event->venue_name }} · {{ $dash::campusLabel($event) }} · <span class="po-scope po-scope--{{ strtolower($event->scope()) }}">{{ $dash::scopeLabel($event->scope()) }}</span></p>
                            </div>
                            <div class="po-list__side">
                                <p class="po-list__date">{{ $event->start_datetime->format('D, M j') }}</p>
                                <p>{{ $event->start_datetime->format('g:i A') }} – {{ $event->end_datetime->format('g:i A') }}</p>
                                <span class="od-badge od-badge--approved">Approved</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section id="selected-date-events" class="od-card">
            <div class="od-card__head">
                <h2 id="selected-date-label" class="od-card__title">Events for selected date</h2>
            </div>
            <div id="event-list"></div>
        </section>
    </div>

    {{-- ===== Recent activity ===== --}}
    <section class="od-card">
        <div class="od-card__head">
            <h2 class="od-card__title">Recent Activity</h2>
            <a href="{{ route('planning_office.notifications') }}" class="od-link">All notifications →</a>
        </div>
        @if($activity->isEmpty())
            <p class="od-empty">No activity recorded yet.</p>
        @else
            <ul class="od-activity">
                @foreach($activity as $log)
                    <li>
                        <span>
                            <span class="od-badge od-badge--{{ $activityBadge[$log->action] ?? 'pending' }}">{{ $activityLabels[$log->action] ?? ucfirst($log->action) }}</span>
                            <strong>{{ $accountNames[$log->email] ?? $log->email }}</strong> — {{ $log->description }}
                        </span>
                        <time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->diffForHumans() }}</time>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ===== Reject / return dialog ===== --}}
    <div id="decision-modal" class="od-modal hidden" role="dialog" aria-modal="true" aria-labelledby="decision-title">
        <div class="od-modal__panel">
            <div class="od-modal__head">
                <h2 id="decision-title" class="od-modal__title">Decision</h2>
                <button type="button" data-decision-close class="od-modal__close" aria-label="Close">✕</button>
            </div>
            <form id="decision-form" method="POST" class="od-modal__body od-form" style="gap:12px; padding-top:18px">
                @csrf
                <p id="decision-event" class="od-muted" style="font-size:13.5px"></p>
                <label class="od-field">
                    <span class="od-label" id="decision-note-label">Planning note</span>
                    <textarea name="planning_note" rows="3" maxlength="2000" class="od-input od-input--sm"></textarea>
                </label>
                <div class="od-modal__actions">
                    <button type="button" data-decision-close class="od-btn od-btn--ghost od-btn--sm">Cancel</button>
                    <button type="submit" id="decision-submit" class="od-btn od-btn--primary od-btn--sm">Confirm</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/event-calendar.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // ---- Calendar ----
            UniEventCalendar.init({
                calendarEl: document.getElementById('planning-office-calendar'),
                events: @json($calendarEvents),
                listEl: document.getElementById('event-list'),
                listLabelEl: document.getElementById('selected-date-label')
            });

            // ---- Pending approvals: search + filters (no reload) ----
            var filterBox = document.getElementById('pending-filters');
            var rows = document.querySelectorAll('#pending-approvals [data-row]');
            var emptyRow = document.querySelector('#pending-approvals [data-empty]');
            function applyFilters() {
                var q = filterBox.querySelector('[data-filter="q"]').value.trim().toLowerCase();
                var status = filterBox.querySelector('[data-filter="status"]').value;
                var scope = filterBox.querySelector('[data-filter="scope"]').value;
                var shown = 0;
                rows.forEach(function (row) {
                    var match = (!q || row.dataset.search.indexOf(q) !== -1)
                        && (!status || row.dataset.status === status)
                        && (!scope || row.dataset.scope === scope);
                    row.classList.toggle('hidden', !match);
                    if (match) shown++;
                });
                if (rows.length) emptyRow.classList.toggle('hidden', shown > 0);
            }
            filterBox.addEventListener('input', applyFilters);
            filterBox.addEventListener('change', applyFilters);

            // ---- Reject / return dialog ----
            var modal = document.getElementById('decision-modal');
            var form = document.getElementById('decision-form');
            var note = form.querySelector('textarea');
            function closeModal() { modal.classList.add('hidden'); }
            document.querySelectorAll('[data-decision]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var isReturn = btn.dataset.decision === 'return';
                    form.action = btn.dataset.action;
                    note.value = '';
                    note.required = isReturn;
                    document.getElementById('decision-title').textContent = isReturn ? 'Return for revision' : 'Reject request';
                    document.getElementById('decision-event').textContent = btn.dataset.title;
                    document.getElementById('decision-note-label').textContent = isReturn
                        ? 'What should the office revise? (required)'
                        : 'Reason for rejecting (optional, shown to the office)';
                    var submit = document.getElementById('decision-submit');
                    submit.textContent = isReturn ? 'Return to office' : 'Reject request';
                    submit.className = 'od-btn od-btn--sm ' + (isReturn ? 'od-btn--primary' : 'od-btn--danger');
                    modal.classList.remove('hidden');
                    note.focus();
                });
            });
            modal.querySelectorAll('[data-decision-close]').forEach(function (b) { b.addEventListener('click', closeModal); });
            modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

            // ---- Analytics ----
            if (typeof Chart === 'undefined') return;
            var charts = @json($charts);
            var seriesColors = ['#4a5690', '#e0a800', '#14b8a6', '#047857', '#8d98d4'];
            var countTicks = { beginAtZero: true, ticks: { precision: 0 } };
            Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
            Chart.defaults.color = '#64698a';

            new Chart(document.getElementById('chart-by-office'), {
                type: 'bar',
                data: { labels: charts.byOffice.labels, datasets: [{ label: 'Events', data: charts.byOffice.values, backgroundColor: charts.byOffice.labels.map(function (l, i) { return seriesColors[i % seriesColors.length]; }), borderRadius: 8 }] },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: countTicks,
                        y: {
                            ticks: {
                                autoSkip: false,
                                callback: function (value) {
                                    var words = this.getLabelForValue(value).split(' ');
                                    var lines = [];
                                    var line = '';
                                    words.forEach(function (word) {
                                        if (line && (line + ' ' + word).length > 32) {
                                            lines.push(line);
                                            line = '';
                                        }
                                        line += (line ? ' ' : '') + word;
                                    });
                                    if (line) lines.push(line);
                                    return lines;
                                }
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('chart-trends'), {
                type: 'line',
                data: {
                    labels: charts.trends.labels,
                    datasets: Object.keys(charts.trends.series).map(function (name, i) {
                        var c = seriesColors[i % seriesColors.length];
                        return { label: name, data: charts.trends.series[name], borderColor: c, backgroundColor: c, tension: .3, pointRadius: 3 };
                    })
                },
                options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10 } } }, scales: { y: countTicks } }
            });

            new Chart(document.getElementById('chart-conflicts'), {
                type: 'bar',
                data: { labels: charts.conflicts.labels, datasets: [{ label: 'Open conflicts', data: charts.conflicts.values, backgroundColor: '#be123c', borderRadius: 8 }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: countTicks } }
            });
        });
    </script>
@endpush
