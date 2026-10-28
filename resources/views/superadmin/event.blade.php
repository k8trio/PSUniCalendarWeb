@extends('layouts.planning-office')

@section('title', $event->title)
@section('heading', 'Event details')
@section('subheading', 'Event information submitted by the office.')

@section('topbar-actions')
    @if(request()->query('q'))
        <a href="{{ route('planning_office.search', ['q' => request()->query('q')]) }}" class="od-btn od-btn--ghost od-btn--sm">Back to search results</a>
    @else
        <a href="{{ route('planning_office.dashboard') }}" class="od-btn od-btn--ghost od-btn--sm">Back to dashboard</a>
    @endif
@endsection

@section('content')
    <article class="od-card po-event-detail">
        <div class="od-card__head">
            <div>
                <h2 class="od-card__title">{{ $event->title }}</h2>
                <p class="po-review__meta">
                    {{ \App\Models\User::UNIVERSITY_OFFICE_ACCOUNT_LABELS[$event->email] ?? $event->name }}
                    · {{ $event->start_datetime?->format('M j, Y') }}
                </p>
            </div>
            <span class="od-badge od-badge--{{ $event->status }}">{{ \App\Models\EventRequest::statusLabel($event->status) }}</span>
        </div>

        <dl class="po-event-details">
            <div>
                <dt>Office contact</dt>
                <dd>{{ $event->name }} <span class="po-sub">{{ $event->email }}</span></dd>
            </div>
            <div>
                <dt>Venue</dt>
                <dd>{{ $event->venue_name }}</dd>
            </div>
            <div>
                <dt>Campus</dt>
                <dd>{{ $event->venueCampus() }}</dd>
            </div>
            <div>
                <dt>Event scope</dt>
                <dd>{{ \App\Services\PlanningOfficeDashboard::scopeLabel($event->scope()) }}</dd>
            </div>
            <div>
                <dt>Start</dt>
                <dd>{{ $event->start_datetime?->format('l, M j, Y · g:i A') ?? 'Not specified' }}</dd>
            </div>
            <div>
                <dt>End</dt>
                <dd>{{ $event->end_datetime?->format('l, M j, Y · g:i A') ?? 'Not specified' }}</dd>
            </div>
            <div class="po-event-details__wide">
                <dt>For (Offices/Officials)</dt>
                <dd>{{ $event->event_for ?: 'Not specified' }}</dd>
            </div>
            <div class="po-event-details__wide">
                <dt>Attention (Persons/Units)</dt>
                <dd>{{ $event->attention_to ?: 'Not specified' }}</dd>
            </div>
            <div class="po-event-details__wide">
                <dt>Event description</dt>
                <dd class="po-event-details__description">{{ $event->description ?: 'No event description provided.' }}</dd>
            </div>
            @if($event->planning_note)
                <div class="po-event-details__wide">
                    <dt>Planning Office note</dt>
                    <dd>{{ $event->planning_note }}</dd>
                </div>
            @endif
        </dl>
    </article>

    <section class="od-card">
        <div class="od-card__head">
            <h2 class="od-card__title">Memo attachment</h2>
            <span class="od-pill">{{ count($event->digital_documents ?? []) }}</span>
        </div>
        @if(!empty($event->digital_documents))
            <ul class="po-list">
                @foreach($event->digital_documents as $document)
                    <li class="po-list__item">
                        <div>
                            <p class="po-list__title">Attached memo {{ $loop->iteration }}</p>
                            <p class="po-list__meta">{{ strtoupper(pathinfo($document, PATHINFO_EXTENSION)) }} file</p>
                        </div>
                        <a class="od-btn od-btn--ghost od-btn--sm" href="{{ route('planning_office.events.documents.show', ['event' => $event, 'documentIndex' => $loop->iteration]) }}" target="_blank" rel="noopener">Advisory Memo</a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="od-empty">No memo or other attachment was submitted for this event.</p>
        @endif
    </section>
@endsection
