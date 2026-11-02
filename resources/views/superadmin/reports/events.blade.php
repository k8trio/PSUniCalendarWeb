@extends('layouts.planning-office')

@section('title', 'Detailed Report')
@section('heading', 'Detailed Report')
@section('subheading', 'Review all office event requests before downloading the PDF.')
@section('topbar-actions')
    <a href="{{ route('planning_office.reports.events', array_filter($reportFilters, fn ($value) => $value !== '')) }}" class="od-btn od-btn--primary od-btn--sm">Download PDF</a>
@endsection

@push('head')
    <link href="{{ asset('css/detailed-report.css') }}" rel="stylesheet">
@endpush

@section('content')
    <section class="od-card report-preview-card">
        <div class="od-card__head">
            <div>
                <h2 class="od-card__title">{{ $reportTitle }}</h2>
                <p class="od-card__sub">Choose an office or select all offices to include in the report.</p>
            </div>
            <a href="{{ route('planning_office.dashboard') }}" class="od-btn od-btn--ghost od-btn--sm">Back to dashboard</a>
        </div>
        <form method="GET" action="{{ route('planning_office.reports.events.preview') }}" class="report-filters">
            <label>
                <span>Office</span>
                <select name="office" class="od-input">
                    <option value="">All offices</option>
                    @foreach($reportOffices as $office)
                        <option value="{{ $office->id }}" @selected((string) $reportFilters['office'] === (string) $office->id)>{{ $office->name }}</option>
                    @endforeach
                    <option value="other" @selected($reportFilters['office'] === 'other')>Other offices</option>
                </select>
            </label>
            <div class="report-filters__actions">
                <button type="submit" class="od-btn od-btn--primary od-btn--sm">Apply filters</button>
                <a href="{{ route('planning_office.reports.events.preview') }}" class="od-btn od-btn--ghost od-btn--sm">Select all</a>
            </div>
        </form>
        @include('reports.event-requests-preview')
    </section>
@endsection
