@extends('layouts.planning-office')

@section('title', 'Detailed Report')
@section('heading', 'Detailed Report')
@section('subheading', 'Review all office event requests before downloading the PDF.')
@section('topbar-actions')
    <a href="{{ route('planning_office.reports.events') }}" class="od-btn od-btn--primary od-btn--sm">Download PDF</a>
@endsection

@push('head')
    <link href="{{ asset('css/detailed-report.css') }}" rel="stylesheet">
@endpush

@section('content')
    <section class="od-card report-preview-card">
        <div class="od-card__head">
            <div>
                <h2 class="od-card__title">{{ $reportTitle }}</h2>
                <p class="od-card__sub">All event requests submitted by university offices.</p>
            </div>
            <a href="{{ route('planning_office.dashboard') }}" class="od-btn od-btn--ghost od-btn--sm">Back to dashboard</a>
        </div>
        @include('reports.event-requests-preview')
    </section>
@endsection
