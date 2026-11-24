@extends('layouts.planning-office')

@section('title', 'Add Event')
@section('heading', 'Add Event')
@section('subheading', 'Add an approved event directly to the university calendar.')

@section('topbar-actions')
    <a href="{{ route('planning_office.dashboard') }}" class="od-btn od-btn--ghost od-btn--sm">Back to dashboard</a>
@endsection

@section('content')
    @if(session()->has('event_added'))
        <section class="od-card" aria-labelledby="event-added-title">
            <div class="od-alert od-alert--success" role="status">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <h2 id="event-added-title" class="od-card__title">Event added successfully</h2>
                    <p><strong>{{ session('event_added') }}</strong> has been added to the calendar.</p>
                </div>
            </div>

            <div class="od-submit-row">
                <a href="{{ route('planning_office.add-event') }}" class="od-btn od-btn--primary">Add another event</a>
                <a href="{{ route('planning_office.dashboard') }}" class="od-btn od-btn--ghost">Done</a>
            </div>
        </section>
    @else
        <section class="od-card">
            <div class="od-card__head">
                <div>
                    <h2 class="od-card__title">Event Details</h2>
                    <p class="od-card__sub">Planning Office entries are approved immediately.</p>
                </div>
            </div>

            <form action="{{ route('planning_office.events.store') }}" method="POST" enctype="multipart/form-data" class="od-form">
                @csrf
                @if($errors->any())
                    <div class="od-alert od-alert--error" role="alert">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="od-form-grid">
                <label class="od-field">
                    <span class="od-label">Office Advisory No.</span>
                    <input
                        type="text"
                        name="office_advisory_no"
                        value="{{ old('office_advisory_no') }}"
                        maxlength="255"
                        class="od-input"
                    >
                </label>

                <label class="od-field">
                    <span class="od-label">Series</span>
                    <input
                        type="text"
                        name="series_text"
                        value="{{ old('series_text') }}"
                        maxlength="255"
                        class="od-input"
                    >
                </label>
            </div>
            
            <div class="od-form-grid">
                <label class="od-field">
                    <span class="od-label">Subject (Event Title)</span>
                    <input type="text" name="title" value="{{ old('title') }}" required class="od-input">
                </label>
                <label class="od-field">
                    <span class="od-label">Venue Name</span>
                    <input type="text" name="venue_name" value="{{ old('venue_name') }}" required autocomplete="off" list="planning-venue-options" class="od-input">
                    <datalist id="planning-venue-options">
                        @foreach($venueOptions as $venueOption)
                            <option value="{{ $venueOption }}"></option>
                        @endforeach
                    </datalist>
                </label>
            </div>

            <div class="od-form-grid">
                <label class="od-field">
                    <span class="od-label">For (Offices/Officials)</span>
                    <textarea name="event_for" rows="3" maxlength="5000" class="od-input" placeholder="Separate offices or officials with semicolons">{{ old('event_for') }}</textarea>
                </label>
                <label class="od-field">
                    <span class="od-label">Attention (Persons/Units)</span>
                    <textarea name="attention_to" rows="3" maxlength="5000" class="od-input" placeholder="Separate persons or units with semicolons">{{ old('attention_to') }}</textarea>
                </label>
            </div>

            <fieldset class="od-audience">
                <legend class="od-label">Who is this event for?</legend>
                <div class="od-audience__options">
                    <label class="od-audience__option">
                        <input type="radio" name="is_university_wide" value="0" {{ old('is_university_wide', '0') !== '1' ? 'checked' : '' }}>
                        <span><strong>Specific Campus Only</strong><small>Visible to offices in the selected campus.</small></span>
                    </label>
                    <label class="od-audience__option">
                        <input type="radio" name="is_university_wide" value="1" {{ old('is_university_wide') === '1' ? 'checked' : '' }}>
                        <span><strong>University Wide</strong><small>Visible to all university offices and campuses.</small></span>
                    </label>
                </div>
            </fieldset>

            <div class="od-form-grid">
                <label class="od-field" id="planning-campus-field">
                    <span class="od-label">Campus</span>
                    <select name="campus" id="planning-campus-select" required class="od-input">
                        <option value="">Select campus</option>
                        @foreach(\App\Models\User::CAMPUSES as $campus)
                            <option value="{{ $campus }}" {{ old('campus') === $campus ? 'selected' : '' }}>{{ $campus }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <fieldset class="od-audience" data-notify-offices>
                <legend class="od-label">Notify offices</legend>
                <p class="od-hint">Selected offices will receive a notification and see this event on their calendar.</p>
                <div class="od-audience__options">
                    <label class="od-audience__option">
                        <input type="checkbox" data-notify-all>
                        <span><strong>All offices</strong><small>Notify every office account.</small></span>
                    </label>
                    @foreach($offices as $office)
                        <label class="od-audience__option">
                            <input type="checkbox" name="notify_offices[]" value="{{ $office->id }}" data-notify-office {{ in_array((string) $office->id, array_map('strval', (array) old('notify_offices', [])), true) ? 'checked' : '' }}>
                            <span><strong>{{ $office->name }}</strong><small>{{ $office->email }}</small></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="od-form-grid">
                <label class="od-field">
                    <span class="od-label">Start Date &amp; Time</span>
                    <input type="datetime-local" name="start_datetime" value="{{ old('start_datetime') }}" required class="od-input">
                </label>
                <label class="od-field">
                    <span class="od-label">End Date &amp; Time</span>
                    <input type="datetime-local" name="end_datetime" value="{{ old('end_datetime') }}" required class="od-input">
                </label>
            </div>

            <label class="od-field">
                <span class="od-label">Description</span>
                <textarea name="description" rows="4" class="od-input">{{ old('description') }}</textarea>
            </label>

            <label class="od-field">
                <span class="od-label">Supporting Documents</span>
                <p class="od-hint">Optional files. Allowed: pdf, jpg, jpeg, png, doc, docx. Maximum size: 10 MB per file.</p>
                <input type="file" name="digital_documents[]" multiple class="od-file">
            </label>

                <div class="od-submit-row">
                    <button type="submit" class="od-btn od-btn--primary">Add Event</button>
                </div>
            </form>
        </section>
    @endif
@endsection

@push('scripts')
@unless(session()->has('event_added'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var campusField = document.getElementById('planning-campus-field');
    var campusSelect = document.getElementById('planning-campus-select');
    var radios = document.querySelectorAll('input[name="is_university_wide"]');

    document.querySelectorAll('[data-notify-offices]').forEach(function (group) {
        var selectAll = group.querySelector('[data-notify-all]');
        var officeCheckboxes = Array.from(group.querySelectorAll('[data-notify-office]'));

        function updateSelectAll() {
            selectAll.checked = officeCheckboxes.length > 0
                && officeCheckboxes.every(function (checkbox) { return checkbox.checked; });
            selectAll.disabled = officeCheckboxes.length === 0;
        }

        selectAll.addEventListener('change', function () {
            officeCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
        officeCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', updateSelectAll);
        });
        updateSelectAll();
    });

    function updateCampus() {
        var universityWide = Array.from(radios).some(function (radio) {
            return radio.checked && radio.value === '1';
        });
        campusField.classList.toggle('hidden', universityWide);
        campusSelect.disabled = universityWide;
        campusSelect.required = !universityWide;
    }

    radios.forEach(function (radio) { radio.addEventListener('change', updateCampus); });
    updateCampus();
});
</script>
@endunless
@endpush
