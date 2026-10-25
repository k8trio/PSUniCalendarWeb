<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniCalendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <link href="{{ asset('css/event-calendar.css') }}" rel="stylesheet">
</head>
<body class="bg-slate-100 min-h-screen">
    {{-- Slide-out menu --}}
    <div id="menu-overlay" class="fixed inset-0 z-40 hidden bg-black/40"></div>
    <aside id="menu-drawer" class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full transform bg-white shadow-xl transition-transform duration-200" aria-hidden="true">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <span class="text-base font-bold text-slate-900">Menu</span>
            <button id="menu-close" type="button" aria-label="Close menu" class="rounded-md p-2 text-slate-500 hover:bg-slate-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24">
                    <path d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>
        <nav class="p-4">
            <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Staff access</p>
            <a href="{{ route('planning_office.login') }}"
               class="block rounded-lg bg-[#0927D8] px-4 py-3 text-sm font-semibold text-white hover:opacity-90">
                Planning Office (Admin) Login
            </a>
            <a href="{{ route('office.login') }}"
               class="mt-2 block rounded-lg border border-[#FFDA27] bg-[#FFDA27]/20 px-4 py-3 text-sm font-semibold text-slate-900 hover:bg-[#FFDA27]/40">
                Office Login
            </a>
        </nav>
    </aside>

    <div class="mx-auto max-w-6xl p-6">
        <div class="flex items-start gap-4">
            <button id="menu-open" type="button" aria-label="Open menu" aria-controls="menu-drawer" aria-expanded="false"
                    class="mt-1 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#0927D8]/40">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div>
                <h1 class="text-3xl font-semibold text-slate-900">Pangasinan State University</h1>
                <p class="mt-2 text-slate-500">University Event Calendar · Published events</p>
            </div>
        </div>

        <div class="mt-8 rounded-lg bg-white p-6 shadow-sm border border-slate-200">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-800">Master Live Calendar</h2>
                <span class="rounded-full bg-[#e6e9f5] px-3 py-1 text-xs font-semibold text-[#3b4679]">Approved events</span>
            </div>

            <div class="mb-4 flex justify-end">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <span>Filter:</span>
                    <select id="campus-filter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#4a5690]/30 focus:border-[#4a5690]">
                        <option value="All Campus">All Campus</option>
                        @foreach(\App\Models\User::CAMPUSES as $campusOption)
                            <option value="{{ $campusOption }}">{{ $campusOption }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div id="calendar" class="min-h-[500px]"></div>
        </div>

        <section class="mt-8 rounded-lg bg-white p-6 shadow-sm border border-slate-200">
            <div class="mb-4 flex items-center justify-between gap-4">
                <h2 class="text-lg font-bold text-gray-800">Published events</h2>
                <span class="text-sm text-slate-500">View only</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($events as $event)
                    <article class="flex flex-wrap items-start justify-between gap-3 py-4">
                        <div>
                            <h3 class="font-semibold text-slate-900">{{ $event['title'] }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ \Illuminate\Support\Carbon::parse($event['start'])->format('D, M j, Y · g:i A') }} · {{ $event['venue'] }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $event['campus'] }}@if($event['description']) · {{ $event['description'] }}@endif</p>
                        </div>
                        @if($event['sdg_number'])
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">SDG {{ $event['sdg_number'] }}</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Not aligned to SDG</span>
                        @endif
                    </article>
                @empty
                    <p class="py-5 text-sm text-slate-500">No events have been published yet.</p>
                @endforelse
            </div>
        </section>

        <div id="selected-date-events" class="mt-8 rounded-lg bg-white p-6 shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <h2 id="selected-date-label" class="text-lg font-bold text-gray-800">Events for selected date</h2>
            </div>
            <div id="event-list"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <script src="{{ asset('js/event-calendar.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            UniEventCalendar.init({
                calendarEl: document.getElementById('calendar'),
                events: {!! $events->toJson() !!},
                campusFilterEl: document.getElementById('campus-filter'),
                listEl: document.getElementById('event-list'),
                listLabelEl: document.getElementById('selected-date-label')
            });
        });
    </script>
    <script>
        (function () {
            const drawer = document.getElementById('menu-drawer');
            const overlay = document.getElementById('menu-overlay');
            const openBtn = document.getElementById('menu-open');
            function setMenu(open) {
                drawer.classList.toggle('-translate-x-full', !open);
                overlay.classList.toggle('hidden', !open);
                drawer.setAttribute('aria-hidden', String(!open));
                openBtn.setAttribute('aria-expanded', String(open));
            }
            openBtn.addEventListener('click', () => setMenu(true));
            document.getElementById('menu-close').addEventListener('click', () => setMenu(false));
            overlay.addEventListener('click', () => setMenu(false));
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setMenu(false); });
        })();
    </script>
</body>
</html>