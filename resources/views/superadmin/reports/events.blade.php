@extends('layouts.planning-office')

@section('title', 'Detailed Report')
@section('heading', 'Detailed Report')
@section('subheading', 'Choose offices to filter the report, or select all to include every office.')

@push('head')
    <link href="{{ asset('css/detailed-report.css') }}" rel="stylesheet">
@endpush

@section('content')
    <section class="od-card report-preview-card">
        <div class="od-card__head">
            <div>
                <h2 class="od-card__title">{{ $reportTitle }}</h2>
                <p class="od-card__sub">Select offices to filter the report, or use Select all to include every office's event requests.</p>
            </div>
            <div class="report-preview-card__actions">
                @if(!$filterError)
                    <a href="{{ route('planning_office.reports.events', $reportFilters['all_offices'] ? ['all_offices' => 1] : ['offices' => $reportFilters['offices']]) }}" class="od-btn od-btn--primary od-btn--sm report-download-button">Download PDF</a>
                @endif
                <a href="{{ route('planning_office.dashboard') }}" class="od-btn od-btn--ghost od-btn--sm">Back to dashboard</a>
            </div>
        </div>
        <form method="GET" action="{{ route('planning_office.reports.events.preview') }}" class="report-filters">
            <input type="hidden" name="filter_applied" value="1">
            @if($filterError)
                <p class="report-filters__error" role="alert">{{ $filterError }}</p>
            @endif
            @error('offices')
                <p class="report-filters__error" role="alert">{{ $message }}</p>
            @enderror
            <div class="report-filters__checks">
                @foreach($reportOffices as $office)
                    <label class="report-filters__check">
                        <input
                            type="checkbox"
                            name="offices[]"
                            value="{{ $office->id }}"
                            @checked(in_array((string) $office->id, $reportFilters['offices'], true))
                        >
                        <span>{{ $office->name }}</span>
                    </label>
                @endforeach
            </div>
            <div class="report-filters__actions">
                <button type="submit" class="od-btn od-btn--primary od-btn--sm">Apply filters</button>
                <a href="{{ route('planning_office.reports.events.preview') }}" class="od-btn od-btn--ghost od-btn--sm">Select all</a>
            </div>
        </form>
        @include('reports.event-requests-preview')
    </section>
@endsection
