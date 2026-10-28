@extends('layouts.planning-office')

@section('title', 'Search')
@section('heading', 'Search')
@section('subheading')
    Results for “{{ $query }}”
@endsection

@section('content')
    @if($offices->isEmpty() && $events->total() === 0)
        <p class="od-empty">No results found.</p>
    @else
        @if($offices->isNotEmpty())
            <section class="od-card">
                <div class="od-card__head">
                    <h2 class="od-card__title">Offices</h2>
                    <span class="od-pill">{{ $offices->count() }}</span>
                </div>
                <ul class="po-list">
                    @foreach($offices as $office)
                        <li class="po-list__item">
                            <div>
                                <a class="po-list__title od-link" href="{{ route('planning_office.offices.show', $office) }}">{{ $office->name }}</a>
                                <p class="po-list__meta">{{ $office->campus ?? 'University office' }}</p>
                            </div>
                            <a class="od-link" href="{{ route('planning_office.offices.show', $office) }}">View office</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($events->total() > 0)
            <section class="od-card">
                <div class="od-card__head">
                    <h2 class="od-card__title">Events</h2>
                    <span class="od-pill">{{ $events->total() }}</span>
                </div>
                <div class="od-table-wrap">
                    <table class="od-table">
                        <thead>
                            <tr>
                                <th scope="col">Event</th>
                                <th scope="col">Organizer</th>
                                <th scope="col">Submitting office</th>
                                <th scope="col">Venue</th>
                                <th scope="col">Schedule</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($events as $event)
                                <tr>
                                    <th scope="row" class="od-table__title">
                                        <a class="od-link" href="{{ route('planning_office.events.show', ['event' => $event, 'q' => $query]) }}">{{ $event->title }}</a>
                                    </th>
                                    <td>{{ $event->name }}</td>
                                    <td>{{ \App\Models\User::UNIVERSITY_OFFICE_ACCOUNT_LABELS[$event->email] ?? $officeNames[$event->email] ?? $event->email }}</td>
                                    <td>{{ $event->venue_name }}</td>
                                    <td>{{ $event->start_datetime?->format('M j, Y · g:i A') }}</td>
                                    <td><span class="od-badge od-badge--{{ $event->status }}">{{ \App\Models\EventRequest::statusLabel($event->status) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="po-pagination">{{ $events->links() }}</div>
            </section>
        @endif
    @endif
@endsection
