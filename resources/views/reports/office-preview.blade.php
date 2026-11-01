<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reportTitle }} - UniCalendar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/office-dashboard.css') }}" rel="stylesheet">
    <link href="{{ asset('css/detailed-report.css') }}" rel="stylesheet">
</head>
<body class="od-body">
    <main class="report-preview-page">
        <header class="report-preview-page__header">
            <a href="{{ route('office.dashboard') }}" class="report-preview-page__brand">UniCalendar · Office Dashboard</a>
            <div class="report-preview-page__actions">
                <a href="{{ route('office.dashboard') }}" class="od-btn od-btn--ghost od-btn--sm">Back to dashboard</a>
                <a href="{{ route('office.requests.report') }}" class="od-btn od-btn--primary od-btn--sm">Download PDF</a>
            </div>
        </header>
        <section class="od-card report-preview-card">
            <div class="od-card__head">
                <div>
                    <h1 class="od-card__title">{{ $reportTitle }}</h1>
                    <p class="od-card__sub">Review your office event requests before downloading the PDF.</p>
                </div>
            </div>
            @include('reports.event-requests-preview')
        </section>
    </main>
</body>
</html>
