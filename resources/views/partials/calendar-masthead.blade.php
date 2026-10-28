<div class="psu-calendar-branding" data-calendar-branding>
    @if($showMenuButton ?? false)
        <button id="menu-open" type="button" aria-label="Open menu" aria-controls="menu-drawer" aria-expanded="false"
                class="psu-calendar-branding__menu inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#0927D8]/40">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24">
                <path d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    @else
        <span aria-hidden="true"></span>
    @endif
    <div class="psu-calendar-branding__center">
        <img class="psu-calendar-branding__seal" src="{{ asset('images/psu-logo.png') }}" alt="Pangasinan State University seal">
        <div class="psu-calendar-branding__text">
            <h1 class="psu-calendar-branding__name">Pangasinan State University</h1>
            <p class="psu-calendar-branding__tagline">University Event Calendar</p>
        </div>
    </div>
    <span class="psu-calendar-branding__spacer" aria-hidden="true"></span>
</div>
