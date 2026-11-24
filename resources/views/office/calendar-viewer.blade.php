<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $viewer->name }} Calendar · UniCalendar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=UnifrakturCook:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <link href="{{ asset('css/event-calendar.css') }}" rel="stylesheet">
    <style>
        body{margin:0;background:#f4f6fb;color:#20243a;font-family:Inter,system-ui,sans-serif}
        .viewer-page{width:min(1440px,calc(100% - 32px));margin:28px auto}
        .viewer-header,.viewer-calendar{border:1px solid #e1e5ef;border-radius:14px;background:#fff;box-shadow:0 12px 32px rgba(27,39,86,.07)}
        .viewer-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px;padding:18px 22px}
        .viewer-brand{display:flex;align-items:center;gap:12px}
        .viewer-brand img{width:48px;height:48px;object-fit:contain}
        .viewer-brand__name{margin:0;color:#26336b;font-size:16px;font-weight:700}
        .viewer-brand__office{margin:4px 0 0;color:#64708c;font-size:13px}
        .viewer-logout{padding:9px 14px;border:0;border-radius:8px;background:#0927d8;color:#fff;font:inherit;font-weight:600;cursor:pointer}
        .viewer-calendar{padding:20px}
        #office-viewer-calendar{min-height:400px}
        @media(max-width:600px){.viewer-page{width:calc(100% - 20px);margin:10px auto}.viewer-header{padding:14px}.viewer-calendar{padding:12px}.viewer-brand__name{font-size:14px}.viewer-brand__office{font-size:11px}}
    </style>
</head>
<body>
    <main class="viewer-page">
        <header class="viewer-header">
            <div class="viewer-brand">
                <img src="{{ asset('images/psu-logo.png') }}" alt="Pangasinan State University seal">
                <div>
                    <p class="viewer-brand__name">Pangasinan State University</p>
                    <p class="viewer-brand__office">{{ $viewer->name }} · View-only calendar</p>
                </div>
            </div>
            <form method="POST" action="{{ route('office.viewer-calendar.logout') }}">
                @csrf
                <button class="viewer-logout" type="submit">Log out</button>
            </form>
        </header>

        <section class="viewer-calendar">
            <div id="office-viewer-calendar">
                @include('partials.calendar-masthead')
            </div>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <script src="{{ asset('js/event-calendar.js') }}?v={{ @filemtime(public_path('js/event-calendar.js')) }}"></script>
    <script>
        UniEventCalendar.init({
            calendarEl: document.getElementById('office-viewer-calendar'),
            events: @js($events),
        });
    </script>
</body>
</html>
