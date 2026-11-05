@php
    $user = auth()->user();
    $nameWords = array_values(array_filter(preg_split('/\s+/', trim(preg_replace('/[^\p{L}\s]/u', ' ', preg_replace('/\([^)]*\)/', '', $user->name)))))) ?: ['O'];
    $nameAbbreviation = preg_match('/\(([\p{L}]{2,4})\)/u', $user->name, $abbr) ? $abbr[1] : null;
    $initials = $nameAbbreviation
        ? mb_strtoupper($nameAbbreviation)
        : mb_strtoupper(mb_substr($nameWords[0], 0, 1) . (count($nameWords) > 1 ? mb_substr(end($nameWords), 0, 1) : ''));
    $navClass = fn (bool $active = false) => 'od-nav-link' . ($active ? ' is-active' : '');
@endphp
<aside id="sidebar" class="od-sidebar" aria-label="Main navigation">
    <div class="od-sidebar__inner">
        <a href="{{ route('office.dashboard') }}#calendar" class="od-brand">
            <span class="od-brand__seal">
                <img src="{{ asset('images/psu-logo.png') }}" alt="Pangasinan State University seal"
                     onerror="this.outerHTML='<span class=&quot;od-seal-fallback&quot;>PSU</span>'" />
            </span>
            <span class="od-brand__text">
                <span class="od-brand__name">UniCalendar</span>
                <span class="od-brand__tag">OFFICE DASHBOARD</span>
            </span>
        </a>

        <p class="od-nav-label">Menu</p>
        <nav class="od-nav">
            <a href="{{ route('office.dashboard') }}#calendar" data-nav-section="calendar" class="{{ $navClass() }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                <span class="od-nav-text">Calendar</span>
            </a>
            <a href="{{ route('office.add-event') }}" data-nav-section="request-form" class="{{ $navClass() }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span class="od-nav-text">Add Event</span>
            </a>
            <a href="{{ route('office.request-status') }}" data-nav-section="request-status" class="{{ $navClass() }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                <span class="od-nav-text">Request Status</span>
            </a>
            <a href="{{ route('office.requests.report.preview') }}" data-nav-section="detailed-report" class="{{ $navClass(true) }}" aria-current="page">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75h5.379a2.25 2.25 0 011.591.659l3.371 3.371a2.25 2.25 0 01.659 1.591v10.879A2.25 2.25 0 0117 22.5H8.25A2.25 2.25 0 016 20.25V6a2.25 2.25 0 012.25-2.25z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 3.75V9h5.25M9 13.5h6m-6 3.75h6" /></svg>
                <span class="od-nav-text">Detailed Report</span>
            </a>
            <a href="{{ route('office.recent-activity') }}" data-nav-section="activity-log" class="{{ $navClass() }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span class="od-nav-text">Recent Activity</span>
            </a>
        </nav>

        <p class="od-nav-label">Account</p>
        <nav class="od-nav">
            <a href="{{ route('office.dashboard') }}" class="{{ $navClass() }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                <span class="od-nav-text">Settings</span>
            </a>
        </nav>

        <div class="od-sidebar__footer">
            <div class="od-account">
                <span class="od-avatar" aria-hidden="true">{{ $initials }}</span>
                <div class="od-account__text">
                    <p class="od-account__name">{{ $user->name }}</p>
                    <p class="od-account__role">Office account</p>
                </div>
            </div>
            <form method="POST" action="{{ route('office.logout') }}">
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
