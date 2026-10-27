@php
    $user = auth()->user();
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    // Avatar initials: a short abbreviation in brackets wins ("... (OP)" → "OP"); otherwise the
    // first and last words, letters only (so "(OVPQA)" never turns into "O(").
    $nameWords = array_values(array_filter(preg_split('/\s+/', trim(preg_replace('/[^\p{L}\s]/u', ' ', preg_replace('/\([^)]*\)/', '', $user->name)))))) ?: ['O'];
    $nameAbbreviation = preg_match('/\(([\p{L}]{2,4})\)/u', $user->name, $abbr) ? $abbr[1] : null;
    $initials = $nameAbbreviation
        ? mb_strtoupper($nameAbbreviation)
        : mb_strtoupper(mb_substr($nameWords[0], 0, 1) . (count($nameWords) > 1 ? mb_substr(end($nameWords), 0, 1) : ''));

    $monthSubmitted = $submittedThisMonth ?? 0;
    $monthApproved = $approvedThisMonth ?? 0;

    $statusLabel = fn ($status) => \App\Models\EventRequest::statusLabel($status);
    $profileFields = ['name', 'email', 'contact_person', 'contact_number', 'profile_password'];
    $profileFormOpen = $errors->hasAny($profileFields) || session('open_profile_form');
    $settingsOpen = $profileFormOpen || $errors->hasAny(['current_password', 'new_password']) || session('open_password_form');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office Dashboard - UniCalendar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <link href="{{ asset('css/event-calendar.css') }}" rel="stylesheet">
    <link href="{{ asset('css/office-dashboard.css') }}" rel="stylesheet">
    <link href="{{ asset('css/ui-feedback.css') }}" rel="stylesheet">
</head>
<body class="od-body {{ ($addEventOnly ?? false) ? 'od-add-event-page' : '' }} {{ ($requestStatusOnly ?? false) ? 'od-request-status-page' : '' }} {{ ($activityOnly ?? false) ? 'od-activity-page' : '' }}">
    <!-- ================= Sidebar ================= -->
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
                <a href="{{ route('office.dashboard') }}#calendar" data-nav-section="calendar" class="od-nav-link">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                    <span class="od-nav-text">Calendar</span>
                </a>
                <a href="{{ route('office.add-event') }}" data-nav-section="request-form" class="od-nav-link">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span class="od-nav-text">Add Event</span>
                </a>
                <a href="{{ route('office.request-status') }}" data-nav-section="request-status" class="od-nav-link">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                    <span class="od-nav-text">Request Status</span>
                </a>
                <a href="{{ route('office.recent-activity') }}" data-nav-section="activity-log" class="od-nav-link">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span class="od-nav-text">Recent Activity</span>
                </a>
            </nav>

            <p class="od-nav-label">Account</p>
            <nav class="od-nav">
                <button type="button" id="settings-btn" class="od-nav-link">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="od-nav-text">Settings</span>
                </button>
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

    <!-- ================= Main ================= -->
    <main class="od-main">
        <div class="od-container">
            <header class="od-topbar">
                <div class="od-topbar__left">
                    <button type="button" id="sidebar-toggle" class="od-icon-btn od-menu-btn" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                    </button>
                    <div>
                        <h1 class="od-topbar__title">{{ ($addEventOnly ?? false) ? (old('edit_id') ? 'Edit Event Request' : 'Add Event') : (($requestStatusOnly ?? false) ? 'Request Status' : (($activityOnly ?? false) ? 'Recent Activity' : 'Office Dashboard')) }}</h1>
                        <p class="od-topbar__sub">{{ now()->format('l, F j, Y') }}</p>
                    </div>
                </div>

                <div class="od-topbar__actions">
                    @if($addEventOnly ?? false)
                        <a href="{{ route('office.dashboard') }}" class="od-btn od-btn--ghost no-print">Back to Dashboard</a>
                    @elseif($requestStatusOnly ?? false)
                        <a href="{{ route('office.dashboard') }}" class="od-btn od-btn--ghost no-print">Back to Dashboard</a>
                    @elseif($activityOnly ?? false)
                        <a href="{{ route('office.dashboard') }}" class="od-btn od-btn--ghost no-print">Back to Dashboard</a>
                    @else
                        <a href="{{ route('office.add-event') }}" class="od-btn od-btn--primary no-print">Add Event</a>
                    @endif
                    <!-- Notification bell -->
                    <div class="od-notif">
                        <button type="button" id="notif-bell-btn" class="od-icon-btn" aria-label="Notifications">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            @if($unreadNotifications->count() > 0)
                                <span class="od-icon-btn__badge">{{ $unreadNotifications->count() }}</span>
                            @endif
                        </button>
                        <div id="notif-dropdown" class="od-dropdown hidden">
                            <div class="od-dropdown__head">
                                <p class="od-dropdown__title">Notifications</p>
                                @if($unreadNotifications->count() > 0)
                                    <form method="POST" action="{{ route('office.notifications.read') }}">
                                        @csrf
                                        <button type="submit" class="od-link">Mark all read</button>
                                    </form>
                                @endif
                            </div>
                            {{-- All notifications: unread (gold dot) first, then earlier ones.
                                 Clicking one opens the "All notifications" window at that item. --}}
                            <div class="od-dropdown__list">
                                @forelse($allNotifications as $n)
                                    <button type="button" class="od-dropdown__item {{ $n->read_at ? '' : 'od-dropdown__item--unread' }}" data-open-notifications="{{ $n->id }}">
                                        <p class="od-dropdown__item-title">{{ $n->title }}</p>
                                        <p class="od-dropdown__item-meta">
                                            Status changed to
                                            <span class="od-text-{{ $n->status }}">{{ mb_strtolower($statusLabel($n->status)) }}</span>
                                            · {{ $n->updated_at?->diffForHumans() }}
                                        </p>
                                        @if($n->planning_note)
                                            <p class="od-dropdown__item-note">{{ \Illuminate\Support\Str::limit($n->planning_note, 90) }}</p>
                                        @endif
                                    </button>
                                @empty
                                    <p class="od-dropdown__empty">No notifications yet.</p>
                                @endforelse
                            </div>
                            @if($allNotifications->isNotEmpty())
                                <button type="button" class="od-dropdown__footer" data-open-notifications>View all notifications</button>
                            @endif
                        </div>
                    </div>
                </div>
            </header>

            <!-- Alerts -->
            @if($reminder)
                <div id="reminder-banner" class="od-alert od-alert--gold">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <p>
                        Upcoming: <strong>{{ $reminder->title }}</strong> at {{ $reminder->venue_name }} on {{ $reminder->start_datetime->format('M j, Y g:i A') }}
                        ({{ $reminder->start_datetime->diffForHumans() }})
                    </p>
                </div>
            @endif
            @include('partials.flash')
            @if($errors->any())
                <div class="od-alert od-alert--error">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Welcome hero -->
            <section class="od-hero">
                <div class="od-hero__text">
                    <p class="od-hero__eyebrow">{{ $greeting }}</p>
                    <h2 class="od-hero__title">{{ $user->name }}</h2>
                    <p class="od-hero__sub">
                        You submitted <strong>{{ $monthSubmitted }}</strong> request(s) this month,
                        <strong>{{ $monthApproved }}</strong> of which were approved.
                    </p>
                </div>
            </section>

            <!-- Stats -->
            <section class="od-stats" aria-label="Summary">
                @foreach([
                    ['Total events', $totalEvents ?? 0, ''],
                    ['Upcoming events', $upcomingEvents ?? 0, ''],
                    ['Pending requests', $pendingCount ?? 0, 'pending'],
                    ['Approved requests', $approvedCount ?? 0, 'approved'],
                    ['Rejected requests', $rejectedCount ?? 0, 'rejected'],
                    ['Cancelled requests', $cancelledCount ?? 0, 'cancelled'],
                ] as [$label, $value, $modifier])
                    <div class="od-stat {{ $modifier ? 'od-stat--' . $modifier : '' }}">
                        <p class="od-stat__label">{{ $label }}</p>
                        <p class="od-stat__value">{{ $value }}</p>
                        <div class="od-stat__bar"></div>
                    </div>
                @endforeach
            </section>

            <!-- ===== Tab: Calendar ===== -->
            <div id="calendar-panel" class="od-calendar-layout">
                <section class="od-card">
                    <div class="od-card__head">
                        <h2 class="od-card__title">Calendar</h2>
                        <span class="od-pill">Approved events</span>
                    </div>
                    <div class="od-calendar-toolbar">
                        <label>
                            <span>Campus:</span>
                            <select id="campus-filter" class="od-input od-input--sm" style="width:auto">
                                <option value="All Campus">All Campus</option>
                                @foreach($calendarCampuses as $campusOption)
                                    <option value="{{ $campusOption }}">{{ $campusOption }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div id="admin-calendar" class="od-calendar"></div>
                </section>
            </div>

            <!-- ===== Tab: Request event ===== -->
            <section id="request-form" class="od-card">
                <div class="od-card__head">
                    <div>
                        <h2 class="od-card__title">{{ old('edit_id') ? 'Edit request' : 'Add Event' }}</h2>
                        <p class="od-card__sub">The Planning Office reviews every request and checks for schedule conflicts.</p>
                    </div>
                </div>

                @if(old('edit_id'))
                    <div class="od-alert od-alert--info" style="justify-content:space-between">
                        <span>Editing request #{{ old('edit_id') }}.</span>
                        <a href="{{ route('office.dashboard') }}" class="od-link">Cancel edit</a>
                    </div>
                @endif

                @if($favoriteVenues->count())
                    <div style="margin-bottom:18px">
                        <p class="od-section-label">Favorite venues</p>
                        <div class="od-chips">
                            @foreach($favoriteVenues as $fav)
                                <button type="button" class="od-chip favorite-venue-chip" data-venue="{{ $fav->venue_name }}" data-campus="{{ $fav->campus }}">★ {{ $fav->venue_name }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form id="venue-request-form" action="{{ route('office.request') }}" method="POST" enctype="multipart/form-data" class="od-form" data-availability-url="{{ route('office.availability') }}">
                    @csrf
                    <input type="hidden" name="edit_id" value="{{ old('edit_id') }}">

                    <div class="od-form-grid">
                        <label class="od-field">
                            <span class="od-label">Subject (Event Title)</span>
                            <input type="text" name="title" value="{{ old('title') }}" required class="od-input" />
                        </label>
                        <label class="od-field">
                            <span class="od-label">
                                Venue Name
                                <button type="button" id="favorite-toggle-btn" class="od-link">☆ Save as favorite</button>
                            </span>
                            <input type="text" id="venue_name_input" name="venue_name" value="{{ old('venue_name') }}" required autocomplete="off" list="venue-options" class="od-input" />
                            <datalist id="venue-options">
                                @foreach($venueOptions ?? [] as $venueOption)
                                    <option value="{{ $venueOption }}"></option>
                                @endforeach
                            </datalist>
                        </label>
                    </div>

                    <div class="od-form-grid">
                        <label class="od-field">
                            <span class="od-label">For (Offices/Officials)</span>
                            <textarea name="event_for" rows="3" maxlength="5000" class="od-input" placeholder="Separate offices or officials with semicolons">{{ old('event_for') }}</textarea>
                        </label>
                        <label class="od-field">
                            <span class="od-label">Attention (Persons/Units)</span>
                            <textarea name="attention_to" rows="3" maxlength="5000" class="od-input" placeholder="Separate persons or units with semicolons">{{ old('attention_to') }}</textarea>
                        </label>
                    </div>
                    <p class="od-hint">Separate entries with semicolons. Both fields stay editable so you can use Backspace to organize or remove entries.</p>

                    {{-- Who can see the event: venue campus only, or every campus --}}
                    <fieldset class="od-audience">
                        <legend class="od-label">Who is this event for?</legend>
                        <div class="od-audience__options">
                            <label class="od-audience__option">
                                <input type="radio" name="is_university_wide" value="0" {{ old('is_university_wide', '0') !== '1' ? 'checked' : '' }} />
                                <span>
                                    <strong>This campus only</strong>
                                    <small>Only offices of the selected campus will see it (e.g. Intrams).</small>
                                </span>
                            </label>
                            <label class="od-audience__option">
                                <input type="radio" name="is_university_wide" value="1" {{ old('is_university_wide') === '1' ? 'checked' : '' }} />
                                <span>
                                    <strong>All campuses</strong>
                                    <small>Every campus sees it, even if it's held at one campus (e.g. a University Meet).</small>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="od-form-grid">
                        <label class="od-field" id="campus-field">
                            <span class="od-label">Campus</span>
                            <select name="campus" id="campus_select" required class="od-input">
                                <option value="">Select campus</option>
                                @foreach(\App\Models\User::CAMPUSES as $campus)
                                    <option value="{{ $campus }}" {{ old('campus') === $campus ? 'selected' : '' }}>{{ $campus }}</option>
                                @endforeach
                            </select>
                        </label>
                        <input type="hidden" name="campus" id="campus_university_wide" value="All Campus" disabled />
                    </div>

                    <div class="od-form-grid">
                        <label class="od-field">
                            <span class="od-label">Start Date &amp; Time</span>
                            <input type="datetime-local" id="start_datetime_input" name="start_datetime" value="{{ old('start_datetime') }}" required class="od-input" />
                        </label>
                        <label class="od-field">
                            <span class="od-label">End Date &amp; Time</span>
                            <input type="datetime-local" id="end_datetime_input" name="end_datetime" value="{{ old('end_datetime') }}" required class="od-input" />
                        </label>
                    </div>

                    <!-- Live conflict & availability check (updates as the form is filled) -->
                    <div id="availability-panel" class="od-avail hidden" data-state="idle" aria-live="polite">
                        <div class="od-avail__head">
                            <span class="od-avail__icon" aria-hidden="true"></span>
                            <div>
                                <p id="availability-title" class="od-avail__title">Live conflict check</p>
                                <p id="availability-message" class="od-avail__message">Enter a venue, campus, and start and end time — availability is checked automatically as you type.</p>
                            </div>
                        </div>
                        <div id="availability-details" class="od-avail__details hidden"></div>
                    </div>

                    <label class="od-field">
                        <span class="od-label">Description</span>
                        <textarea name="description" rows="4" class="od-input">{{ old('description') }}</textarea>
                    </label>

                    <label class="od-field">
                        <span class="od-label">Supporting Documents</span>
                        <p class="od-hint">Optional files for the request. Allowed: pdf, jpg, jpeg, png, doc, docx. @if(old('edit_id'))Leave empty to keep the previously uploaded files.@endif</p>
                        <input type="file" name="digital_documents[]" multiple class="od-file" />
                    </label>

                    <div class="od-submit-row">
                        <button type="submit" id="venue-request-submit" class="od-btn od-btn--primary">{{ old('edit_id') ? 'Update Request' : 'Add Event' }}</button>
                        <p id="submit-conflict-note" class="od-submit-note hidden">This venue and time are already booked. Choose another venue or time to continue.</p>
                    </div>
                </form>
            </section>

            <!-- ===== Tab: Request Status ===== -->
            @if(!($activityOnly ?? false))
            <section id="request-status" class="od-card">
                <div class="od-card__head">
                    <h2 class="od-card__title">Request Status</h2>
                    <button type="button" onclick="window.print()" class="od-btn od-btn--ghost od-btn--sm no-print">Print / Save as PDF</button>
                </div>

                <form id="request-filter-form" method="GET" action="{{ ($requestStatusOnly ?? false) ? route('office.request-status') : route('office.dashboard') . '#request-status' }}" class="no-print">
                    <div class="od-filters">
                        <input type="search" name="q" value="{{ $filterQuery ?? '' }}" placeholder="Search title or venue..." autocomplete="off" class="od-input od-input--sm" aria-label="Search title or venue" />
                        <select name="status" class="od-input od-input--sm" aria-label="Status">
                            @foreach(['all' => 'All status', 'pending' => 'Pending', 'conflict' => 'Conflict review', 'approved' => 'Approved', 'returned' => 'Returned for revision', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $value => $label)
                                <option value="{{ $value }}" {{ ($filterStatus ?? 'all') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="from" value="{{ $filterFrom ?? '' }}" class="od-input od-input--sm" aria-label="From date" />
                        <input type="date" name="to" value="{{ $filterTo ?? '' }}" class="od-input od-input--sm" aria-label="To date" />
                    </div>
                    <div class="od-filters__foot">
                        <button type="reset" class="od-btn od-btn--ghost od-btn--sm">Reset</button>
                        <span id="request-filter-count" class="od-muted"></span>
                    </div>
                </form>

                <div class="od-table-wrap">
                    <table class="od-table">
                        <thead>
                            <tr>
                                <th>Event Title</th>
                                <th>Venue</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($officeRequests ?? [] as $r)
                                <tr class="request-row"
                                    data-search="{{ mb_strtolower($r->title . ' ' . $r->venue_name) }}"
                                    data-status="{{ $r->status }}"
                                    data-date="{{ $r->start_datetime?->toDateString() }}">
                                    <td class="od-table__title">{{ $r->title }}</td>
                                    <td>{{ $r->venue_name }}</td>
                                    <td>{{ $r->start_datetime?->format('M d, Y') }}</td>
                                    <td><span class="od-badge od-badge--{{ $r->status }}">{{ $statusLabel($r->status) }}</span></td>
                                    <td class="no-print">
                                        <div class="od-table__actions">
                                            <a href="{{ route('office.requests.duplicate', $r->id) }}" class="od-link">Duplicate</a>
                                            @if(in_array($r->status, \App\Models\EventRequest::EDITABLE_STATUSES))
                                                <span class="od-table__sep">·</span>
                                                <a href="{{ route('office.requests.edit', $r->id) }}" class="od-link">Edit</a>
                                                <span class="od-table__sep">·</span>
                                                <form method="POST" action="{{ route('office.requests.cancel', $r->id) }}" onsubmit="return confirm('Cancel this request?');">
                                                    @csrf
                                                    <button type="submit" class="od-link od-link--danger">Cancel</button>
                                                </form>
                                            @endif
                                            @if($r->status === 'cancelled')
                                                <span class="od-table__sep">·</span>
                                                <form method="POST" action="{{ route('office.requests.delete', $r->id) }}" onsubmit="return confirm('Permanently delete this cancelled request? This cannot be undone.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="od-link od-link--danger">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            <tr id="request-filter-empty" class="hidden">
                                <td colspan="5" class="od-table__empty">No requests match your filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </section>
            @endif

            @if(!($requestStatusOnly ?? false))
            <section id="activity-log" class="od-card od-request-activity no-print">
                <div class="od-card__head">
                    <h2 class="od-card__title">Recent Activity</h2>
                </div>
                @if(($recentActivity ?? collect())->isEmpty())
                    <p class="od-empty">No activity yet.</p>
                @else
                    <ul class="od-activity">
                        @foreach($recentActivity as $log)
                            <li>
                                <span>{{ $log->description }}</span>
                                <time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('M j, Y g:i A') }}</time>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
            @endif
        </div>
    </main>

    <!-- ================= All notifications modal ================= -->
    <div id="notifications-modal" class="od-modal hidden" role="dialog" aria-modal="true" aria-labelledby="notifications-title">
        <div class="od-modal__panel od-modal__panel--wide">
            <div class="od-modal__head">
                <h2 id="notifications-title" class="od-modal__title">All notifications</h2>
                <button type="button" data-notifications-close class="od-modal__close" aria-label="Close notifications">✕</button>
            </div>

            <div class="od-modal__body">
                <div class="od-notif-summary">
                    <p class="od-muted">
                        {{ $allNotifications->count() }} notification(s)@if($unreadNotifications->count()) · <strong>{{ $unreadNotifications->count() }} unread</strong>@endif
                    </p>
                    @if($unreadNotifications->count() > 0)
                        <form method="POST" action="{{ route('office.notifications.read') }}">
                            @csrf
                            <button type="submit" class="od-link">Mark all read</button>
                        </form>
                    @endif
                </div>

                <ul class="od-notif-list">
                    @forelse($allNotifications as $n)
                        <li id="notification-{{ $n->id }}" class="od-notif {{ $n->read_at ? '' : 'od-notif--unread' }}">
                            <div class="od-notif__top">
                                <p class="od-notif__title">{{ $n->title }}</p>
                                <span class="od-badge od-badge--{{ $n->status }}">{{ $statusLabel($n->status) }}</span>
                            </div>
                            <p class="od-notif__meta">
                                The Planning Office {{ ['conflict' => 'flagged a schedule conflict on', 'returned' => 'returned for revision'][$n->status] ?? $n->status }} this request
                                · <time datetime="{{ $n->updated_at?->toIso8601String() }}" title="{{ $n->updated_at?->format('M j, Y g:i A') }}">{{ $n->updated_at?->diffForHumans() }}</time>
                            </p>
                            <p class="od-notif__event">
                                {{ $n->venue_name }}@if($n->campus) · {{ $n->campus }}@endif
                                · {{ $n->start_datetime?->format('M j, Y g:i A') }}
                                @if($n->end_datetime)
                                    – {{ $n->end_datetime->isSameDay($n->start_datetime) ? $n->end_datetime->format('g:i A') : $n->end_datetime->format('M j, Y g:i A') }}
                                @endif
                            </p>
                            @if($n->planning_note)
                                <p class="od-timeline__note">Planning Office: {{ $n->planning_note }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="od-empty">No notifications yet. You'll be notified here when the Planning Office approves or rejects a request.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <!-- ================= Settings modal ================= -->
    <div id="settings-modal" class="od-modal {{ $settingsOpen ? '' : 'hidden' }}" role="dialog" aria-modal="true" aria-labelledby="settings-title">
        <div class="od-modal__panel">
            <div class="od-modal__head">
                <h2 id="settings-title" class="od-modal__title">Settings</h2>
                <button type="button" data-settings-close class="od-modal__close" aria-label="Close settings">✕</button>
            </div>

            <div class="od-modal__body">
                <!-- Office profile -->
                <div id="office-profile" class="od-modal__section">
                    <div class="od-modal__section-head">
                        <h3 class="od-modal__section-title">Office profile</h3>
                        <div id="profile-view-actions" class="od-modal__links {{ $profileFormOpen ? 'hidden' : '' }}">
                            <button type="button" id="confidential-toggle" class="od-link">Show confidential info</button>
                            <button type="button" id="profile-edit-btn" class="od-link">Edit</button>
                        </div>
                    </div>

                    <form id="profile-form" class="od-form {{ $profileFormOpen ? '' : 'hidden' }}" style="gap:12px" method="POST" action="{{ route('office.profile.update') }}">
                        @csrf
                        @method('PATCH')
                        @foreach([
                            ['name', 'Office Name', 'text', $user->name, true],
                            ['contact_person', 'Contact Person', 'text', $user->contact_person, false],
                            ['contact_number', 'Contact Number', 'tel', $user->contact_number, false],
                            ['email', 'Email', 'email', $user->email, true],
                        ] as [$field, $label, $type, $value, $required])
                            <label class="od-field">
                                <span class="od-label">{{ $label }}</span>
                                <input type="{{ $type }}" name="{{ $field }}" value="{{ old($field, $value) }}" {{ $required ? 'required' : '' }} class="od-input od-input--sm" />
                                @error($field)
                                    <span class="od-error">{{ $message }}</span>
                                @enderror
                            </label>
                        @endforeach
                        <label class="od-field">
                            <span class="od-label">Current Password <span class="od-muted" style="font-weight:400">(required to save changes)</span></span>
                            <input type="password" name="profile_password" required autocomplete="current-password" class="od-input od-input--sm" />
                            @error('profile_password')
                                <span class="od-error">{{ $message }}</span>
                            @enderror
                        </label>
                        <div class="od-modal__actions">
                            <button type="button" id="profile-cancel-btn" class="od-btn od-btn--ghost od-btn--sm">Cancel</button>
                            <button type="submit" class="od-btn od-btn--primary od-btn--sm">Save profile</button>
                        </div>
                    </form>

                    <dl id="profile-view" class="od-profile-grid {{ $profileFormOpen ? 'hidden' : '' }}" style="margin:0">
                        <div>
                            <dt>Office Name</dt>
                            <dd>{{ $user->name }}</dd>
                        </div>
                        <div>
                            <dt>Contact Person</dt>
                            <dd class="confidential-value" data-value="{{ $user->contact_person ?: '—' }}">••••••••</dd>
                        </div>
                        <div>
                            <dt>Contact Number</dt>
                            <dd class="confidential-value" data-value="{{ $user->contact_number ?: '—' }}">••••••••</dd>
                        </div>
                        <div>
                            <dt>Email</dt>
                            <dd class="confidential-value" data-value="{{ $user->email }}">••••••••</dd>
                        </div>
                    </dl>
                </div>

                <!-- Change password -->
                <div class="od-modal__section">
                    <div class="od-modal__section-head">
                        <h3 class="od-modal__section-title">Change password</h3>
                    </div>
                    <form id="password-form" class="od-form" style="gap:12px" method="POST" action="{{ route('office.password.update') }}">
                        @csrf
                        @method('PATCH')
                        <label class="od-field">
                            <span class="od-label">Current Password</span>
                            <input type="password" name="current_password" required autocomplete="current-password" class="od-input od-input--sm" />
                            @error('current_password')
                                <span class="od-error">{{ $message }}</span>
                            @enderror
                        </label>
                        <label class="od-field">
                            <span class="od-label">New Password</span>
                            <input type="password" name="new_password" required minlength="8" autocomplete="new-password" class="od-input od-input--sm" />
                            @error('new_password')
                                <span class="od-error">{{ $message }}</span>
                            @enderror
                        </label>
                        <label class="od-field">
                            <span class="od-label">Confirm New Password</span>
                            <input type="password" name="new_password_confirmation" required minlength="8" autocomplete="new-password" class="od-input od-input--sm" />
                        </label>
                        <div class="od-modal__actions">
                            <button type="button" data-settings-close class="od-btn od-btn--ghost od-btn--sm">Cancel</button>
                            <button type="submit" class="od-btn od-btn--primary od-btn--sm">Update password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <script src="{{ asset('js/event-calendar.js') }}"></script>
    <script src="{{ asset('js/ui-feedback.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarPanel = document.getElementById('calendar-panel');
            var requestForm = document.getElementById('request-form');
            var requestStatus = document.getElementById('request-status');
            var activityLog = document.getElementById('activity-log');
            var navLinks = document.querySelectorAll('[data-nav-section]');
            var calendar;

            function escapeHtml(value) {
                return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            function setActiveSection(section) {
                navLinks.forEach(function(link) {
                    var isActive = link.dataset.navSection === section;
                    link.classList.toggle('is-active', isActive);
                    if (isActive) {
                        link.setAttribute('aria-current', 'page');
                    } else {
                        link.removeAttribute('aria-current');
                    }
                });

                if (calendarPanel) {
                    calendarPanel.classList.toggle('hidden', section !== 'calendar');
                }

                if (requestForm) {
                    requestForm.classList.toggle('hidden', section !== 'request-form');
                }

                if (requestStatus) {
                    requestStatus.classList.toggle('hidden', section !== 'request-status');
                }

                if (activityLog) {
                    activityLog.classList.toggle('hidden', section !== 'activity-log');
                }

                if (calendar && section === 'calendar') {
                    requestAnimationFrame(function() {
                        calendar.updateSize();
                    });
                }

                if (section === 'activity-log') {
                    requestAnimationFrame(function () {
                        if (activityLog) activityLog.scrollIntoView({ block: 'start' });
                    });
                }
            }

            function sectionFromHash() {
                if (window.location.hash === '#calendar') {
                    return 'calendar';
                }

                if (window.location.hash === '#request-form') {
                    return 'request-form';
                }

                if (window.location.hash === '#request-status') {
                    return 'request-status';
                }

                if (window.location.hash === '#activity-log') {
                    return 'activity-log';
                }

                if (@json($addEventOnly ?? false)) {
                    return 'request-form';
                }

                if (@json($requestStatusOnly ?? false)) {
                    return 'request-status';
                }

                if (@json($activityOnly ?? false)) {
                    return 'activity-log';
                }

                // A filtered Request Status search always lands back on that tab
                var params = new URLSearchParams(window.location.search);
                if (['q', 'status', 'from', 'to'].some(function (key) { return params.has(key); })) {
                    return 'request-status';
                }

                return 'calendar';
            }

            // ---- Request Status filters: instant, no page reload ----
            var requestFilterForm = document.getElementById('request-filter-form');
            if (requestFilterForm) {
                var requestRows = document.querySelectorAll('.request-row');
                var requestFilterEmpty = document.getElementById('request-filter-empty');
                var requestFilterCount = document.getElementById('request-filter-count');

                var applyRequestFilters = function (e) {
                    var q = requestFilterForm.q.value.trim().toLowerCase();
                    var status = requestFilterForm.status.value;
                    var from = requestFilterForm.from.value;
                    var to = requestFilterForm.to.value;
                    var shown = 0;

                    requestRows.forEach(function (row) {
                        var date = row.dataset.date;
                        var match = (!q || row.dataset.search.indexOf(q) !== -1)
                            && (status === 'all' || row.dataset.status === status)
                            && (!from || (date && date >= from))
                            && (!to || (date && date <= to));
                        row.classList.toggle('hidden', !match);
                        if (match) shown++;
                    });

                    requestFilterEmpty.classList.toggle('hidden', shown > 0);
                    requestFilterCount.textContent = 'Showing ' + shown + ' of ' + requestRows.length;

                    // Keep the URL in sync so refresh/bookmark keeps the filters
                    var params = new URLSearchParams();
                    if (q) params.set('q', q);
                    if (status !== 'all') params.set('status', status);
                    if (from) params.set('from', from);
                    if (to) params.set('to', to);
                    var qs = params.toString();
                    if (e) history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : '') + '#request-status');
                };

                requestFilterForm.addEventListener('input', applyRequestFilters);
                requestFilterForm.addEventListener('change', applyRequestFilters);
                requestFilterForm.addEventListener('submit', function (e) { e.preventDefault(); });
                requestFilterForm.addEventListener('reset', function () {
                    // Reset restores the initial (URL) values, so clear fields explicitly
                    setTimeout(function () {
                        requestFilterForm.q.value = '';
                        requestFilterForm.status.value = 'all';
                        requestFilterForm.from.value = '';
                        requestFilterForm.to.value = '';
                        applyRequestFilters(true);
                    });
                });

                applyRequestFilters();
            }

            setActiveSection(sectionFromHash());

            window.addEventListener('hashchange', function() {
                setActiveSection(sectionFromHash());
            });

            // ---- Mobile sidebar ----
            var sidebar = document.getElementById('sidebar');
            var sidebarToggle = document.getElementById('sidebar-toggle');
            var sidebarOverlay = document.getElementById('sidebar-overlay');
            var setSidebarOpen = function (open) {
                sidebar.classList.toggle('is-open', open);
                sidebarOverlay.classList.toggle('hidden', !open);
                sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            };
            sidebarToggle.addEventListener('click', function () { setSidebarOpen(true); });
            sidebarOverlay.addEventListener('click', function () { setSidebarOpen(false); });
            sidebar.querySelectorAll('.od-nav-link').forEach(function (link) {
                link.addEventListener('click', function () { setSidebarOpen(false); });
            });

            // Shared calendar: titles on the calendar, multi-day spans, click for details
            var calendarElement = document.getElementById('admin-calendar');
            if (calendarElement) {
                calendar = UniEventCalendar.init({
                    calendarEl: calendarElement,
                    events: {!! isset($events) ? $events->toJson() : '[]' !!},
                    campusFilterEl: document.getElementById('campus-filter')
                });
            }

            // ---- Notification bell dropdown ----
            var bellBtn = document.getElementById('notif-bell-btn');
            var notifDropdown = document.getElementById('notif-dropdown');
            if (bellBtn && notifDropdown) {
                bellBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('hidden');
                });
                document.addEventListener('click', function (e) {
                    if (!notifDropdown.contains(e.target) && !bellBtn.contains(e.target)) {
                        notifDropdown.classList.add('hidden');
                    }
                });
                // ---- "All notifications" window (opened from a notification or "View all") ----
                var notifModal = document.getElementById('notifications-modal');
                if (notifModal) {
                    var closeNotifModal = function () { notifModal.classList.add('hidden'); };

                    notifDropdown.querySelectorAll('[data-open-notifications]').forEach(function (trigger) {
                        trigger.addEventListener('click', function () {
                            notifDropdown.classList.add('hidden');
                            notifModal.classList.remove('hidden');
                            notifModal.querySelectorAll('.od-notif--focused').forEach(function (el) { el.classList.remove('od-notif--focused'); });

                            // Highlight and scroll to the notification that was clicked
                            var target = trigger.dataset.openNotifications && document.getElementById('notification-' + trigger.dataset.openNotifications);
                            if (target) {
                                target.classList.add('od-notif--focused');
                                target.scrollIntoView({ block: 'nearest' });
                            }
                            notifModal.querySelector('.od-modal__close').focus();
                        });
                    });

                    notifModal.querySelectorAll('[data-notifications-close]').forEach(function (btn) {
                        btn.addEventListener('click', closeNotifModal);
                    });
                    notifModal.addEventListener('click', function (e) {
                        if (e.target === notifModal) closeNotifModal();
                    });
                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape') closeNotifModal();
                    });
                }
            }

            // ---- Office profile: show/hide confidential info ----
            var confidentialToggle = document.getElementById('confidential-toggle');
            var confidentialValues = document.querySelectorAll('.confidential-value');
            var confidentialVisible = false;
            if (confidentialToggle) {
                confidentialToggle.addEventListener('click', function () {
                    confidentialVisible = !confidentialVisible;
                    confidentialValues.forEach(function (el) {
                        el.textContent = confidentialVisible ? el.dataset.value : '••••••••';
                    });
                    confidentialToggle.textContent = confidentialVisible ? 'Hide confidential info' : 'Show confidential info';
                });
            }

            // ---- Settings: edit office profile ----
            var profileForm = document.getElementById('profile-form');
            var profileView = document.getElementById('profile-view');
            var profileViewActions = document.getElementById('profile-view-actions');
            var setProfileEditing = function (editing) {
                if (!profileForm) return;
                profileForm.classList.toggle('hidden', !editing);
                profileView.classList.toggle('hidden', editing);
                profileViewActions.classList.toggle('hidden', editing);
                if (!editing) profileForm.reset();
            };
            var profileEditBtn = document.getElementById('profile-edit-btn');
            var profileCancelBtn = document.getElementById('profile-cancel-btn');
            if (profileEditBtn) profileEditBtn.addEventListener('click', function () { setProfileEditing(true); });
            if (profileCancelBtn) profileCancelBtn.addEventListener('click', function () { setProfileEditing(false); });

            // ---- Settings modal ----
            var settingsBtn = document.getElementById('settings-btn');
            var settingsModal = document.getElementById('settings-modal');
            if (settingsBtn && settingsModal) {
                var closeSettings = function () {
                    settingsModal.classList.add('hidden');
                    // Re-mask credentials whenever settings is closed
                    if (confidentialVisible && confidentialToggle) confidentialToggle.click();
                    setProfileEditing(false);
                };
                settingsBtn.addEventListener('click', function () {
                    setSidebarOpen(false);
                    settingsModal.classList.remove('hidden');
                    settingsModal.querySelector('.od-modal__close').focus();
                });
                settingsModal.querySelectorAll('[data-settings-close]').forEach(function (btn) {
                    btn.addEventListener('click', closeSettings);
                });
                settingsModal.addEventListener('click', function (e) {
                    if (e.target === settingsModal) closeSettings();
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') return;
                    if (!settingsModal.classList.contains('hidden')) closeSettings();
                    setSidebarOpen(false);
                });
            }

            // ---- Live conflict & availability check ----
            var venueForm = document.getElementById('venue-request-form');
            var availPanel = document.getElementById('availability-panel');
            if (venueForm && availPanel) {
                var availUrl = venueForm.dataset.availabilityUrl;
                var venueInput = document.getElementById('venue_name_input');
                var campusSelect = document.getElementById('campus_select');
                var campusField = document.getElementById('campus-field');
                var universityWideCampus = document.getElementById('campus_university_wide');
                var audienceInputs = venueForm.querySelectorAll('input[name="is_university_wide"]');
                var startInput = document.getElementById('start_datetime_input');
                var endInput = document.getElementById('end_datetime_input');
                var submitButton = document.getElementById('venue-request-submit');
                var editIdInput = venueForm.querySelector('input[name="edit_id"]');
                var availTitle = document.getElementById('availability-title');
                var availMessage = document.getElementById('availability-message');
                var availDetails = document.getElementById('availability-details');
                var submitNote = document.getElementById('submit-conflict-note');
                var availTimer = null;
                var availController = null;
                var lastQuery = null;

                var setAvail = function (state, title, message, detailsHtml) {
                    availPanel.dataset.state = state;
                    availPanel.classList.toggle('hidden', !['conflict', 'error', 'invalid'].includes(state));
                    availTitle.textContent = title;
                    availMessage.textContent = message;
                    availDetails.innerHTML = detailsHtml || '';
                    availDetails.classList.toggle('hidden', !detailsHtml);
                    submitNote.classList.toggle('hidden', state !== 'conflict');
                    submitButton.disabled = ['conflict', 'checking', 'invalid'].includes(state);
                };

                var isUniversityWide = function () {
                    return venueForm.querySelector('input[name="is_university_wide"]:checked').value === '1';
                };

                var syncCampusScope = function () {
                    var universityWide = isUniversityWide();
                    campusField.classList.toggle('hidden', universityWide);
                    campusSelect.disabled = universityWide;
                    campusSelect.required = !universityWide;
                    universityWideCampus.disabled = !universityWide;
                };

                audienceInputs.forEach(function (input) {
                    input.addEventListener('change', function () {
                        syncCampusScope();
                        scheduleAvailabilityCheck();
                    });
                });
                syncCampusScope();

                var renderBooking = function (event) {
                    return '<li class="od-avail__item">' +
                        '<div><p class="od-avail__item-title">' + escapeHtml(event.title) + '</p>' +
                        '<p class="od-avail__item-meta">' + escapeHtml(event.office) + ' · ' + escapeHtml(event.time) + '</p></div>' +
                        '<span class="od-badge od-badge--' + escapeHtml(event.status) + '">' + (event.status === 'conflict' ? 'Conflict review' : escapeHtml(event.status.charAt(0).toUpperCase() + event.status.slice(1))) + '</span>' +
                        '</li>';
                };

                var runAvailabilityCheck = function () {
                    var venue = venueInput.value.trim();
                    var start = startInput.value;
                    var end = endInput.value;
                    var universityWide = isUniversityWide();

                    if (!venue || !start || !end || (!universityWide && !campusSelect.value)) {
                        lastQuery = null;
                        setAvail('idle', 'Live conflict check', 'Enter a venue, campus, and start and end time — availability is checked automatically as you type.');
                        return;
                    }
                    if (end <= start) {
                        lastQuery = null;
                        setAvail('invalid', 'Check the time', 'The end date and time must be after the start.');
                        return;
                    }
                    var startTime = start.slice(11);
                    var endTime = end.slice(11);
                    if (startTime < '08:00' || startTime >= '17:00' || endTime < '08:00' || endTime > '17:00') {
                        lastQuery = null;
                        setAvail('invalid', 'Outside office hours', 'Events must start and end between 8:00 AM and 5:00 PM. Multi-day events are allowed within these hours.');
                        return;
                    }

                    var params = new URLSearchParams({
                        venue_name: venue,
                        campus: universityWide ? 'All Campus' : campusSelect.value,
                        is_university_wide: universityWide ? '1' : '0',
                        start_datetime: start,
                        end_datetime: end
                    });
                    if (editIdInput && editIdInput.value) params.set('edit_id', editIdInput.value);

                    var query = params.toString();
                    if (query === lastQuery) return;
                    lastQuery = query;

                    // Cancel any older in-flight check so a slow reply can't overwrite a newer one
                    if (availController) availController.abort();
                    availController = new AbortController();

                    setAvail('checking', 'Checking availability…', 'Looking for bookings at ' + venue + '.');

                    fetch(availUrl + '?' + query, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        signal: availController.signal
                    })
                        .then(function (res) {
                            if (!res.ok) throw new Error('HTTP ' + res.status);
                            return res.json();
                        })
                        .then(function (data) {
                            var html = '';
                            var pastNote = data.in_past ? ' Note: this start time is in the past.' : '';

                            if (data.state === 'conflict') {
                                html += '<p class="od-section-label">Conflicting bookings</p><ul class="od-avail__list">' +
                                    data.conflicts.map(renderBooking).join('') + '</ul>';

                                if (data.suggestion) {
                                    html += '<div class="od-avail__suggest">' +
                                        '<div><p class="od-section-label" style="margin:0">Next available slot</p>' +
                                        '<p class="od-avail__suggest-time">' + escapeHtml(data.suggestion.label) + '</p></div>' +
                                        '<button type="button" class="od-btn od-btn--primary od-btn--sm" data-suggest-start="' + escapeHtml(data.suggestion.start) + '" data-suggest-end="' + escapeHtml(data.suggestion.end) + '">Use this time</button>' +
                                        '</div>';
                                } else {
                                    html += '<p class="od-avail__none">No free slot of the same length in the next 14 days — try another venue.</p>';
                                }

                                setAvail('conflict',
                                    'Conflict detected',
                                    data.venue + ' is already booked for ' + data.conflicts.length + ' overlapping event' + (data.conflicts.length > 1 ? 's' : '') + ' at ' + data.requested + '.' + pastNote,
                                    html);
                                return;
                            }

                            if (data.day.events.length) {
                                html += '<p class="od-section-label">Other bookings at this venue on ' + escapeHtml(data.day.label) + '</p><ul class="od-avail__list">' +
                                    data.day.events.map(renderBooking).join('') + '</ul>';
                            }

                            setAvail('available',
                                'Venue available',
                                data.venue + ' is free for ' + data.requested + '.' + pastNote,
                                html);
                        })
                        .catch(function (err) {
                            if (err.name === 'AbortError') return;
                            lastQuery = null;
                            setAvail('error', 'Could not check availability', 'Availability could not be verified. Your submission will still be checked on the server for scheduling conflicts.');
                        });
                };

                var scheduleAvailabilityCheck = function () {
                    clearTimeout(availTimer);
                    if (availController) {
                        availController.abort();
                        availController = null;
                    }
                    lastQuery = null;
                    setAvail('idle', 'Live conflict check', 'Enter a venue, campus, and start and end time — availability is checked automatically as you type.');
                    availTimer = setTimeout(runAvailabilityCheck, 400);
                };

                venueForm.addEventListener('submit', function (event) {
                    if (availPanel.dataset.state === 'conflict' || availPanel.dataset.state === 'checking') {
                        event.preventDefault();
                        availPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
    
                [venueInput, campusSelect, startInput, endInput].forEach(function (field) {
                    field.addEventListener('input', scheduleAvailabilityCheck);
                    field.addEventListener('change', scheduleAvailabilityCheck);
                });

                // "Use this time" fills in the suggested free slot and re-checks
                availDetails.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-suggest-start]');
                    if (!btn) return;
                    startInput.value = btn.dataset.suggestStart;
                    endInput.value = btn.dataset.suggestEnd;
                    runAvailabilityCheck();
                    startInput.focus();
                });

                // Pre-filled form (edit / duplicate / validation error): check right away
                runAvailabilityCheck();
            }

            // ---- Favorite venue quick-select chips ----
            document.querySelectorAll('.favorite-venue-chip').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    var venueField = document.getElementById('venue_name_input');
                    venueField.value = chip.dataset.venue;
                    if (chip.dataset.campus) {
                        document.getElementById('campus_select').value = chip.dataset.campus;
                    }
                    venueField.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            // ---- Save current venue as favorite ----
            var favToggleBtn = document.getElementById('favorite-toggle-btn');
            if (favToggleBtn) {
                favToggleBtn.addEventListener('click', function () {
                    var venue = document.getElementById('venue_name_input').value.trim();
                    var campus = document.getElementById('campus_select').value;
                    if (!venue) {
                        alert('Type a venue name first.');
                        return;
                    }

                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = "{{ route('office.favorite-venues.toggle') }}";
                    form.innerHTML = '@csrf' +
                        '<input type="hidden" name="venue_name" value="' + venue.replace(/"/g, '&quot;') + '">' +
                        '<input type="hidden" name="campus" value="' + campus.replace(/"/g, '&quot;') + '">';
                    document.body.appendChild(form);
                    form.submit();
                });
            }
        });
    </script>
</body>
</html>
