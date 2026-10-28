@extends('layouts.planning-office')

@section('title', 'Archive')
@section('heading', 'Event Archive')
@section('subheading', 'Approved events and analytics from the three most recent completed academic years.')

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;

            var analytics = @json($analytics);
            var seriesColors = ['#4a5690', '#e0a800', '#14b8a6', '#047857', '#8d98d4'];
            var countAxis = { beginAtZero: true, ticks: { precision: 0 } };

            Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
            Chart.defaults.color = '#64698a';

            new Chart(document.getElementById('archive-campus-chart'), {
                type: 'bar',
                data: {
                    labels: Object.keys(analytics.campuses),
                    datasets: [{ label: 'Events', data: Object.values(analytics.campuses), backgroundColor: Object.keys(analytics.campuses).map(function (_, index) { return seriesColors[index % seriesColors.length]; }), borderRadius: 8 }]
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: countAxis,
                        y: { ticks: { autoSkip: false, font: { size: 11 }, padding: 8 } }
                    }
                }
            });

            new Chart(document.getElementById('archive-monthly-chart'), {
                type: 'line',
                data: {
                    labels: Object.keys(analytics.monthly),
                    datasets: [{ label: 'Approved events', data: Object.values(analytics.monthly), borderColor: '#4a5690', backgroundColor: '#4a5690', tension: .3, pointRadius: 3 }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, padding: 8, font: { size: 10 } } } },
                    scales: {
                        x: { ticks: { maxRotation: 45, minRotation: 30, autoSkip: true, maxTicksLimit: 8, font: { size: 10 } } },
                        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } }
                    }
                }
            });

            new Chart(document.getElementById('archive-scope-chart'), {
                type: 'pie',
                data: { labels: ['University-wide', 'Campus-specific'], datasets: [{ label: 'Approved events', data: [analytics.universityWide, analytics.campusSpecific], backgroundColor: ['#047857', '#56639f'] }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        });
    </script>
@endpush

@section('content')
    <section class="od-card">
        <div class="od-card__head">
            <div>
                <h2 class="od-card__title">Academic Year {{ $selectedYear['label'] }}</h2>
                <p class="od-card__sub">{{ $selectedYear['start']->format('M j, Y') }} – {{ $selectedYear['end']->format('M j, Y') }}</p>
            </div>
            <form method="GET" action="{{ route('planning_office.archive') }}" class="po-archive-filter">
                <label class="od-field" for="archive-year">
                    <span class="od-label">Academic year</span>
                    <select id="archive-year" name="year" class="od-input od-input--sm" onchange="this.form.submit()">
                        @foreach($academicYears as $academicYear)
                            <option value="{{ $academicYear['label'] }}" {{ $academicYear['label'] === $selectedYear['label'] ? 'selected' : '' }}>
                                {{ $academicYear['label'] }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </form>
        </div>

        <section class="od-stats po-stats po-archive-stats" aria-label="Archive analytics">
            <article class="od-stat">
                <p class="od-stat__label">Approved events</p>
                <p class="od-stat__value">{{ $analytics['total'] }}</p>
            </article>
            <article class="od-stat od-stat--approved">
                <p class="od-stat__label">University-wide</p>
                <p class="od-stat__value">{{ $analytics['universityWide'] }}</p>
            </article>
            <article class="od-stat">
                <p class="od-stat__label">Campus-specific</p>
                <p class="od-stat__value">{{ $analytics['campusSpecific'] }}</p>
            </article>
            <article class="od-stat od-stat--rejected">
                <p class="od-stat__label">Resolved scheduling conflicts</p>
                <p class="od-stat__value">{{ $analytics['resolvedConflicts'] }}</p>
            </article>
        </section>

        <div class="od-card__head">
            <h3 class="od-card__title">Analytics</h3>
        </div>
        <section class="po-charts" aria-label="Approved event analytics charts">
            <figure class="po-chart-card po-chart-card--office">
                <figcaption>Approved events by campus</figcaption>
                <div class="po-chart po-chart--office"><canvas id="archive-campus-chart" aria-label="Approved events by campus"></canvas></div>
            </figure>
            <figure class="po-chart-card">
                <figcaption>Approved event trends <span>· monthly</span></figcaption>
                <div class="po-chart"><canvas id="archive-monthly-chart" aria-label="Monthly approved event trends"></canvas></div>
            </figure>
            <figure class="po-chart-card">
                <figcaption>Approved event scope <span>· university-wide and campus-specific</span></figcaption>
                <div class="po-chart"><canvas id="archive-scope-chart" aria-label="University-wide and campus-specific approved events"></canvas></div>
            </figure>
        </section>

        @if($events->isEmpty())
            <p class="od-empty">No approved events are archived for this academic year.</p>
        @else
            <div class="od-card__head po-archive-events-head">
                <h3 class="od-card__title">Approved events</h3>
                <span class="od-pill">{{ $events->total() }} events</span>
            </div>
            <div class="od-table-wrap">
                <table class="od-table">
                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Campus / venue</th>
                            <th scope="col">Schedule</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($events->groupBy(fn ($event) => $event->start_datetime->format('F Y')) as $month => $monthEvents)
                            <tr class="po-archive-month">
                                <th scope="colgroup" colspan="4">{{ $month }}</th>
                            </tr>
                            @foreach($monthEvents as $event)
                                <tr>
                                    <th scope="row" class="od-table__title">
                                        <a class="od-link" href="{{ route('planning_office.events.show', $event) }}">{{ $event->title }}</a>
                                        <span class="po-sub">{{ $event->name }}</span>
                                    </th>
                                    <td>{{ \App\Services\PlanningOfficeDashboard::campusLabel($event) }}<span class="po-sub">{{ $event->venue_name }}</span></td>
                                    <td>
                                        {{ $event->start_datetime->format('M j, Y') }}
                                        <span class="po-sub">{{ $event->start_datetime->format('g:i A') }} – {{ $event->end_datetime->format('g:i A') }}</span>
                                    </td>
                                    <td><span class="od-badge od-badge--approved">Approved</span></td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="po-pagination">{{ $events->links() }}</div>
        @endif
    </section>
@endsection
