<div class="report-preview">
    <div class="report-preview__summary">
        <p>{{ $events->count() }} event request(s)</p>
        @isset($reportFilterLabel)
            <p>Included: {{ $reportFilterLabel }}</p>
        @endisset
        @if($officeName)
            <p>Office: {{ $officeName }}</p>
        @endif
    </div>

    @forelse($events as $index => $event)
        <article class="report-preview__event {{ $showPostEndDetails ? '' : 'report-preview__event--compact' }}">
            <header class="report-preview__event-heading">
                <span class="report-preview__number">Event {{ $index + 1 }}</span>
                <h2>{{ $event->title ?: 'Untitled event' }}</h2>
                @if($showPostEndDetails)
                    <span class="report-preview__status">{{ \App\Models\EventRequest::statusLabel($event->status) }}</span>
                @endif
            </header>
            <dl class="report-preview__fields">
                <div><dt>Event title</dt><dd>{{ $event->title ?: '—' }}</dd></div>
                <div><dt>Venue</dt><dd>{{ $event->venue_name ?: '—' }}</dd></div>
                @unless($showPostEndDetails)
                    <div><dt>Submitted by</dt><dd>{{ $event->name ?: '—' }}</dd></div>
                    <div>
                        <dt>Memo</dt>
                        <dd>{{ count($event->digital_documents ?? []) > 0 ? 'Attached memo' : 'None' }}</dd>
                    </div>
                @endunless
                @if($showPostEndDetails)
                    @if($includeOfficeColumn)
                        <div><dt>Office</dt><dd>{{ $event->report_office_name }}</dd></div>
                    @endif
                    <div><dt>Submitted by</dt><dd>{{ $event->name ?: '—' }}</dd></div>
                    <div><dt>For (Offices/Officials)</dt><dd>{{ $event->event_for ?: '—' }}</dd></div>
                    <div><dt>Attention (Persons/Units)</dt><dd>{{ $event->attention_to ?: '—' }}</dd></div>
                    <div><dt>Campus</dt><dd>{{ $event->venueCampus() }}</dd></div>
                    <div><dt>Event scope</dt><dd>{{ \App\Services\PlanningOfficeDashboard::scopeLabel($event->scope()) }}</dd></div>
                @endif
                @unless($showPostEndDetails)
                    <div>
                        <dt>Event date</dt>
                        <dd>{{ ($event->start_datetime?->format('F j, Y') ?: 'Not specified') . ' – ' . ($event->end_datetime?->format('F j, Y') ?: 'Not specified') }}</dd>
                    </div>
                @else
                    <div><dt>Start</dt><dd>{{ $event->start_datetime?->format('l, F j, Y · g:i A') ?: 'Not specified' }}</dd></div>
                    <div><dt>End</dt><dd>{{ $event->end_datetime?->format('l, F j, Y · g:i A') ?: 'Not specified' }}</dd></div>
                @endunless
                @if($showPostEndDetails)
                    <div><dt>Description</dt><dd>{{ $event->description ?: 'No event description provided.' }}</dd></div>
                    <div><dt>Planning Office note</dt><dd>{{ $event->planning_note ?: '—' }}</dd></div>
                    <div>
                        <dt>Supporting documents</dt>
                        <dd>
                            @forelse($event->digital_documents ?? [] as $document)
                                {{ basename($document) }}@unless($loop->last), @endunless
                            @empty
                                None attached
                            @endforelse
                        </dd>
                    </div>
                @endif
            </dl>
        </article>
    @empty
        <div class="report-preview__empty">No event requests were found.</div>
    @endforelse
</div>
