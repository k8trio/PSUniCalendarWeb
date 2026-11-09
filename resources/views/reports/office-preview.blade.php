<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reportTitle }} - UniCalendar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=UnifrakturCook:wght@700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/office-dashboard.css') }}" rel="stylesheet">
    <link href="{{ asset('css/detailed-report.css') }}" rel="stylesheet">
</head>
<body class="od-body">
    @include('office.partials.sidebar')
    <main class="od-main">
        <div class="od-container">
            <header class="od-topbar">
                <div class="od-topbar__left">
                    <button type="button" id="sidebar-toggle" class="od-icon-btn od-menu-btn" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                    </button>
                    <div>
                        <h1 class="od-topbar__title">Detailed Report</h1>
                        <p class="od-topbar__sub">Review your office event requests before downloading the PDF.</p>
                    </div>
                </div>
                <div class="od-topbar__actions">
                    <a href="{{ route('office.dashboard') }}" class="od-btn od-btn--ghost od-btn--sm">Back to dashboard</a>
                    <a href="{{ route('office.requests.report') }}" class="od-btn od-btn--primary od-btn--sm">Download PDF</a>
                </div>
            </header>
            <section class="od-card report-preview-card">
                <div class="od-card__head">
                    <div>
                        <h2 class="od-card__title">{{ $reportTitle }}</h2>
                        <p class="od-card__sub">Review your office event requests before downloading the PDF.</p>
                    </div>
                </div>
                @include('reports.event-requests-preview')
            </section>
        </div>
    </main>
    @include('office.partials.request-history-commands')
    <script src="{{ asset('js/office-request-history-shortcuts.js') }}"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const setSidebarOpen = open => {
            sidebar.classList.toggle('is-open', open);
            sidebarOverlay.classList.toggle('hidden', !open);
            sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        sidebarToggle.addEventListener('click', () => setSidebarOpen(true));
        sidebarOverlay.addEventListener('click', () => setSidebarOpen(false));
        sidebar.querySelectorAll('.od-nav-link').forEach(link => {
            link.addEventListener('click', () => setSidebarOpen(false));
        });
    </script>
</body>
</html>
