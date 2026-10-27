@php
    $user = auth()->user();
    // Avatar initials: same rule as the Office dashboard
    $nameWords = array_values(array_filter(preg_split('/\s+/', trim(preg_replace('/[^\p{L}\s]/u', ' ', preg_replace('/\([^)]*\)/', '', $user->name)))))) ?: ['P'];
    $nameAbbreviation = preg_match('/\(([\p{L}]{2,4})\)/u', $user->name, $abbr) ? $abbr[1] : null;
    $initials = $nameAbbreviation
        ? mb_strtoupper($nameAbbreviation)
        : mb_strtoupper(mb_substr($nameWords[0], 0, 1) . (count($nameWords) > 1 ? mb_substr(end($nameWords), 0, 1) : ''));

    $currentOfficeId = (int) optional(request()->route('office'))->id;
    $navClass = fn (bool $active) => 'od-nav-link' . ($active ? ' is-active' : '');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Planning Office') - UniCalendar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/office-dashboard.css') }}" rel="stylesheet">
    <link href="{{ asset('css/planning-office.css') }}" rel="stylesheet">
    <link href="{{ asset('css/ui-feedback.css') }}" rel="stylesheet">
    @stack('head')
</head>
<body class="od-body">
    <!-- ================= Sidebar ================= -->
    <aside id="sidebar" class="od-sidebar" aria-label="Main navigation">
        <div class="od-sidebar__inner">
            <a href="{{ route('planning_office.dashboard') }}" class="od-brand">
                <span class="od-brand__seal">
                    <img src="{{ asset('images/psu-logo.png') }}" alt="Pangasinan State University seal"
                         onerror="this.outerHTML='<span class=&quot;od-seal-fallback&quot;>PSU</span>'" />
                </span>
                <span class="od-brand__text">
                    <span class="od-brand__name">UniCalendar</span>
                    <span class="od-brand__tag">PLANNING OFFICE</span>
                </span>
            </a>

            <p class="od-nav-label">Menu</p>
            <nav class="od-nav">
                <a href="{{ route('planning_office.dashboard') }}" class="{{ $navClass(request()->routeIs('planning_office.dashboard', 'planning_office.calendar')) }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                    <span class="od-nav-text">Dashboard</span>
                </a>
                <a href="{{ route('planning_office.pending') }}" class="{{ $navClass(request()->routeIs('planning_office.pending')) }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span class="od-nav-text">Review requests</span>
                    @if($sidebarNotificationCount > 0)
                        <span class="po-nav-count">{{ $sidebarNotificationCount }}</span>
                    @endif
                </a>
                <a href="{{ route('planning_office.venues') }}" class="{{ $navClass(request()->routeIs('planning_office.venues*')) }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                    <span class="od-nav-text">Venues</span>
                </a>
            </nav>

            {{-- University offices only; campus groups are omitted. --}}
            <p class="od-nav-label">Offices</p>
            <nav class="od-nav">
                @foreach($sidebarOfficeGroups as $officeGroup => $offices)
                    @php($officeGroupOpen = $offices->contains('id', $currentOfficeId))
                    <details class="po-group" {{ $officeGroupOpen ? 'open' : '' }}>
                        <summary class="{{ $navClass($officeGroupOpen) }}">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
                            <span class="od-nav-text po-group__name">{{ $officeGroup }}</span>
                            <span class="po-nav-count po-nav-count--muted">{{ $offices->count() }}</span>
                        </summary>
                        <div class="po-subnav">
                            @forelse($offices as $office)
                                <a href="{{ route('planning_office.offices.show', $office) }}" class="po-subnav__link {{ $currentOfficeId === $office->id ? 'is-active' : '' }}" title="{{ $office->name }}">{{ $office->name }}</a>
                            @empty
                                <p class="po-subnav__empty">No offices yet</p>
                            @endforelse
                        </div>
                    </details>
                @endforeach
            </nav>

            <p class="od-nav-label">Account</p>
            <nav class="od-nav">
                <a href="{{ route('planning_office.notifications') }}" class="{{ $navClass(request()->routeIs('planning_office.notifications')) }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                    <span class="od-nav-text">Notifications</span>
                </a>
                <a href="{{ route('planning_office.settings') }}" class="{{ $navClass(request()->routeIs('planning_office.settings*')) }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="od-nav-text">Settings</span>
                </a>
            </nav>

            <div class="od-sidebar__footer">
                <form method="POST" action="{{ route('planning_office.logout') }}">
                    @csrf
                    <button type="submit" class="od-logout">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                        <span class="od-nav-text">Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>
    <div id="sidebar-overlay" class="od-sidebar-overlay hidden"></div>

    <!-- ================= Main ================= -->
    <main class="od-main">
        <div class="od-container">
            <header class="od-topbar">
                <div class="od-topbar__left">
                    <button type="button" id="sidebar-toggle" class="od-icon-btn od-menu-btn" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                    </button>
                    <div>
                        <h1 class="od-topbar__title">
                            @hasSection('heading')
                                @yield('heading')
                            @else
                                @yield('title', 'Planning Office')
                            @endif
                        </h1>
                        <p class="od-topbar__sub">@yield('subheading', now()->format('l, F j, Y'))</p>
                    </div>
                </div>

                <div class="od-topbar__actions">
                    @yield('topbar-actions')
                    @if(request()->routeIs('planning_office.notifications', 'planning_office.search'))
                        <a href="{{ route('planning_office.dashboard') }}" class="od-btn od-btn--ghost od-btn--sm po-topbar-back">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                            </svg>
                            Back to dashboard
                        </a>
                    @endif
                    <form action="{{ route('planning_office.search') }}" method="GET" class="po-global-search" role="search">
                        <label class="sr-only" for="planning-office-search">Search offices and events</label>
                        <input
                            id="planning-office-search"
                            type="search"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Search offices, events..."
                            aria-label="Search offices, organizers, event titles, and venues"
                            required
                        >
                        <button type="submit" aria-label="Search">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="10.8" cy="10.8" r="6.8"></circle>
                                <path stroke-linecap="round" d="m16 16 4.5 4.5"></path>
                            </svg>
                        </button>
                    </form>
                    <a href="{{ route('planning_office.notifications') }}" class="od-icon-btn" aria-label="Notifications{{ $sidebarNotificationCount ? ' (' . $sidebarNotificationCount . ' awaiting review)' : '' }}">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        @if($sidebarNotificationCount > 0)
                            <span class="od-icon-btn__badge">{{ $sidebarNotificationCount }}</span>
                        @endif
                    </a>
                </div>
            </header>

            @include('partials.flash')

            @yield('content')
        </div>
    </main>

    <script>
        (function () {
            var sidebar = document.getElementById('sidebar');
            var toggle = document.getElementById('sidebar-toggle');
            var overlay = document.getElementById('sidebar-overlay');
            if (!sidebar || !toggle || !overlay) return;

            function setOpen(open) {
                sidebar.classList.toggle('is-open', open);
                overlay.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            toggle.addEventListener('click', function () { setOpen(true); });
            overlay.addEventListener('click', function () { setOpen(false); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') setOpen(false);
            });
        })();
    </script>
    <script src="{{ asset('js/ui-feedback.js') }}"></script>
    @stack('scripts')
</body>
</html>
