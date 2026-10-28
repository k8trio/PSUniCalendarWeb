@extends('layouts.planning-office')

@section('title', 'Review Requests')
@section('heading', 'Request review')
@section('subheading', 'Check the schedule, resolve any conflicts, and record the planning decision.')

@section('topbar-actions')
    <a href="{{ route('planning_office.dashboard') }}" class="od-btn od-btn--ghost od-btn--sm">Back to dashboard</a>
@endsection

@section('content')
        @if($errors->any())
            <div class="od-alert od-alert--error">{{ $errors->first() }}</div>
        @endif

        @forelse($requests as $request)
            <article id="request-{{ $request->id }}" class="od-card">
                <div class="po-review__head">
                    <div>
                        <div class="od-card__title" style="flex-wrap:wrap">
                            <h2 class="po-review__title">{{ $request->title }}</h2>
                            <span class="od-badge od-badge--{{ $request->status === 'conflict' ? 'conflict' : 'pending' }}">{{ $request->status === 'conflict' ? 'Conflict detected' : 'Awaiting review' }}</span>
                        </div>
                        <p class="po-review__meta">{{ $request->name }} · {{ $request->email }} · {{ $request->campus ?? 'Campus not specified' }}</p>
                    </div>
                </div>

                <div class="po-review__body">
                    <div class="po-review__info">
                        <p><strong>Requested schedule:</strong><br>{{ $request->start_datetime->format('D, M j, Y · g:i A') }} – {{ $request->end_datetime->format('g:i A') }}</p>
                        <p><strong>Venue:</strong> {{ $request->venue_name }}</p>
                        @if($request->event_for)
                            <p><strong>For (Offices/Officials):</strong> {{ $request->event_for }}</p>
                        @endif
                        @if($request->attention_to)
                            <p><strong>Attention (Persons/Units):</strong> {{ $request->attention_to }}</p>
                        @endif
                        <p class="od-muted" style="font-size:13.5px">{{ $request->description ?: 'No event description provided.' }}</p>
                        @if($request->planning_note)<p class="od-timeline__note" style="margin:0">Previous note: {{ $request->planning_note }}</p>@endif
                        @if($request->digital_documents)
                            <div class="po-review__docs">
                                @foreach($request->digital_documents as $document)
                                    <a class="od-link" href="{{ asset('storage/' . $document) }}" target="_blank" rel="noopener">Document {{ $loop->iteration }}</a>
                                @endforeach
                            </div>
                        @endif
                        @if($request->conflicts->isNotEmpty())
                            <div class="po-conflict">
                                <p><strong style="color:inherit">Overlapping event request(s)</strong></p>
                                @foreach($request->conflicts as $conflict)
                                    <p>{{ $conflict->title }} · {{ $conflict->start_datetime->format('M j, g:i A') }} – {{ $conflict->end_datetime->format('g:i A') }}</p>
                                @endforeach
                            </div>
                        @else
                            <p class="po-review__ok">No venue or time conflicts detected.</p>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('planning_office.approve', ['id' => $request->id]) }}" class="po-review__form">
                        @csrf
                        <label class="od-field">
                            <span class="od-label">Venue</span>
                            <input name="venue_name" value="{{ $request->venue_name }}" required class="od-input od-input--sm">
                        </label>
                        <label class="od-field">
                            <span class="od-label">Campus</span>
                            <select name="campus" required class="od-input od-input--sm">
                                @foreach([...\App\Models\User::MAIN_CAMPUSES, 'All Campus'] as $campus)
                                    <option value="{{ $campus }}" {{ $request->campus === $campus ? 'selected' : '' }}>{{ $campus }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="od-field po-span">
                            <span class="od-label">Who is this event for?</span>
                            <select name="is_university_wide" class="od-input od-input--sm">
                                <option value="0" {{ $request->is_university_wide ? '' : 'selected' }}>This campus only — only offices of the selected campus see it</option>
                                <option value="1" {{ $request->is_university_wide ? 'selected' : '' }}>All campuses — every campus sees it, wherever it's held</option>
                            </select>
                        </label>
                        <label class="od-field">
                            <span class="od-label">Start</span>
                            <input type="datetime-local" name="start_datetime" value="{{ $request->start_datetime->format('Y-m-d\\TH:i') }}" required class="od-input od-input--sm">
                        </label>
                        <label class="od-field">
                            <span class="od-label">End</span>
                            <input type="datetime-local" name="end_datetime" value="{{ $request->end_datetime->format('Y-m-d\\TH:i') }}" required class="od-input od-input--sm">
                        </label>
                        <label class="od-field po-span">
                            <span class="od-label">Planning note</span>
                            <textarea name="planning_note" rows="2" class="od-input od-input--sm" placeholder="Optional note for the requester">{{ $request->planning_note }}</textarea>
                        </label>
                        <div class="po-review__actions po-span">
                            <button type="submit" class="od-btn od-btn--primary od-btn--sm">Save and approve</button>
                            <button formaction="{{ route('planning_office.return', ['id' => $request->id]) }}" formmethod="post" formnovalidate type="submit" class="od-btn od-btn--ghost od-btn--sm" title="Uses the planning note as the revision instructions">Return for revision</button>
                            <button formaction="{{ route('planning_office.reject', ['id' => $request->id]) }}" formmethod="post" type="submit" class="od-btn od-btn--danger od-btn--sm">Reject request</button>
                        </div>
                    </form>
                </div>
            </article>
        @empty
            <div class="od-card"><p class="od-empty">No requests are awaiting review.</p></div>
        @endforelse
@endsection
