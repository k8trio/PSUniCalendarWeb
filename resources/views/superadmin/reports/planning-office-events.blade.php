@extends('layouts.planning-office')

@section('title', 'Planning Office Added Events Report')
@section('heading', 'Planning Office Added Events Report')
@section('subheading', 'Detailed report of events entered directly by the Planning Office.')

@section('topbar-actions')
    <a href="{{ route('planning_office.dashboard') }}" class="od-btn od-btn--ghost od-btn--sm">Back to dashboard</a>
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

        @forelse($events as $event)
            <article class="od-card" style="margin-top:16px">
                <h3 class="od-card__title">{{ $event->title ?: 'Untitled event' }}</h3>

                <dl>
                    <div>
                        <dt>Office Advisory No.</dt>
                        <dd>{{ $event->office_advisory_no ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Series</dt>
                        <dd>{{ $event->series_text ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Date</dt>
                        <dd>
                            {{ $event->start_datetime?->format('F j, Y') ?: 'Not specified' }}
                            @if($event->end_datetime && $event->end_datetime->format('Y-m-d') !== $event->start_datetime?->format('Y-m-d'))
                                – {{ $event->end_datetime->format('F j, Y') }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Subject</dt>
                        <dd>{{ $event->title ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Memo</dt>
                        <dd>{{ $event->description ?: 'No memo provided.' }}</dd>
                    </div>
                    <div>
                        <dt>Memo attachments</dt>
                        <dd>
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
                                    >{{ basename($document) }}</a>@unless($loop->last), @endunless
                                @else
                                    {{ is_string($document) ? 'Attachment unavailable: ' . basename($document) : 'Invalid attachment' }}
                                @endif
                            @empty
                                No attachments
                            @endforelse
                        </dd>
                    </div>
                </dl>
            </article>
        @empty
            <p class="od-empty">No events added directly by the Planning Office were found.</p>
        @endforelse
    </section>
@endsection