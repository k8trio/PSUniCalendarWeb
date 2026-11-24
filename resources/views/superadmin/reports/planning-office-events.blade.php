@extends('layouts.planning-office')

@section('title', 'Planning Office Added Events Report')
@section('heading', 'Planning Office Added Events Report')
@section('subheading', 'Events entered directly by the Planning Office.')

@section('topbar-actions')
    <a href="{{ route('planning_office.dashboard') }}" class="od-btn od-btn--ghost od-btn--sm">
        Back to dashboard
    </a>
@endsection

@section('content')
    <section class="od-card">
        <div class="od-card__head">
            <div>
                <h2 class="od-card__title">Planning Office Added Events</h2>
                <p class="od-card__sub">{{ $events->count() }} event(s)</p>
            </div>

            <a href="{{ route('planning_office.reports.added-events') }}" class="od-btn od-btn--primary od-btn--sm">
                Download PDF
            </a>
        </div>

        <div class="od-table-wrap">
            <table class="od-table po-added-events-report">
                <thead>
                    <tr>
                        <th scope="col">Office Advisory No.</th>
                        <th scope="col">Series</th>
                        <th scope="col">Subject</th>
                        <th scope="col">When Posted</th>
                        <th scope="col">Advisory Memo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $event)
                        <tr>
                            <td>{{ $event->office_advisory_no ?: '—' }}</td>
                            <td>{{ $event->series_text ?: '—' }}</td>
                            <td>{{ $event->title ?: 'Untitled event' }}</td>
                            <td>
                                {{ $event->created_at?->format('M j, Y') ?: '—' }}
                                @if($event->created_at)
                                    <span class="po-added-events-report__time">
                                        {{ $event->created_at->format('g:i A') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @forelse($event->digital_documents ?? [] as $document)
                                    @php
                                        $validDocument = is_string($document)
                                            && str_starts_with($document, 'event-documents/')
                                            && basename($document) === substr($document, strlen('event-documents/'))
                                            && \Illuminate\Support\Facades\Storage::disk('public')->exists($document);
                                    @endphp

                                    @if($validDocument)
                                        <a
                                            href="{{ route('planning_office.events.documents.show', ['event' => $event, 'documentIndex' => $loop->iteration]) }}"
                                            target="_blank"
                                            rel="noopener"
                                        >{{ basename($document) }}</a>@unless($loop->last)<br>@endunless
                                    @else
                                        <span>Attachment unavailable</span>@unless($loop->last)<br>@endunless
                                    @endif
                                @empty
                                    No memo attached
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="od-table__empty">
                                No events added directly by the Planning Office were found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <style>
        .po-added-events-report {
            width: 100%;
            table-layout: fixed;
        }

        .po-added-events-report th,
        .po-added-events-report td {
            overflow-wrap: anywhere;
            vertical-align: top;
        }

        .po-added-events-report th:nth-child(1) { width: 17%; }
        .po-added-events-report th:nth-child(2) { width: 14%; }
        .po-added-events-report th:nth-child(3) { width: 25%; }
        .po-added-events-report th:nth-child(4) { width: 17%; }
        .po-added-events-report th:nth-child(5) { width: 27%; }

        .po-added-events-report__time {
            display: block;
            color: #626985;
            font-size: 12px;
        }
    </style>
@endsection