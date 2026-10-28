/*
 * UniCalendar — shared event calendar (FullCalendar 6)
 *
 * Used by the public calendar, the Office dashboard, the Admin dashboard and the
 * Planning Office dashboard. It:
 *   - shows each event's start time and title on its start date only
 *     (multi-day events are not drawn across the following days),
 *   - opens a details window when an event is clicked (with the full start and end),
 *   - lists the events that start on a clicked day.
 *
 * Times: the app stores dates exactly as they were typed (e.g. 8:00 AM) but Laravel
 * serialises them as UTC. Everything here is therefore displayed in UTC so the
 * times appear unchanged instead of shifting by the viewer's timezone offset.
 *
 * Usage:
 *   UniEventCalendar.init({
 *       calendarEl: el, events: [...],            // required
 *       officeFilterEl: select,                     // optional "All Offices" filter
 *       listEl: el, listLabelEl: el,               // optional "Events for selected date" panel
 *   });
 */
(function () {
    'use strict';

    var TZ = 'UTC';
    var DAY_MS = 86400000;

    // ---------- helpers ----------

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fmt(date, options) {
        return date.toLocaleString('en-US', Object.assign({ timeZone: TZ }, options));
    }

    function fmtTime(date) {
        return fmt(date, { hour: 'numeric', minute: '2-digit' });
    }

    function fmtDay(date) {
        return fmt(date, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }

    function dayKey(date) {                      // "YYYY-MM-DD" in display time
        return date.toISOString().slice(0, 10);
    }

    function keyToDate(key) {
        return new Date(key + 'T00:00:00Z');
    }

    /** Start/end dates plus how many calendar days the event covers. */
    function span(ev) {
        var start = new Date(ev.start);
        var end = ev.end ? new Date(ev.end) : start;
        var startKey = dayKey(start);
        var endKey = dayKey(end);
        var totalDays = Math.round((keyToDate(endKey) - keyToDate(startKey)) / DAY_MS) + 1;

        return { start: start, end: end, startKey: startKey, endKey: endKey, totalDays: totalDays };
    }

    function whenText(ev) {
        var s = span(ev);
        if (s.totalDays === 1) {
            return fmtDay(s.start) + ' · ' + fmtTime(s.start) + ' – ' + fmtTime(s.end);
        }
        return fmtDay(s.start) + ', ' + fmtTime(s.start) + ' → ' + fmtDay(s.end) + ', ' + fmtTime(s.end);
    }

    // ---------- details window (one per page) ----------

    var modal = null;
    var lastFocus = null;

    function ensureModal() {
        if (modal) return modal;

        modal = document.createElement('div');
        modal.className = 'ec-modal';
        modal.hidden = true;
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'ec-modal-title');
        modal.innerHTML =
            '<div class="ec-modal__panel">' +
                '<div class="ec-modal__head">' +
                    '<div>' +
                        '<p class="ec-modal__eyebrow">Event details</p>' +
                        '<h2 id="ec-modal-title" class="ec-modal__title"></h2>' +
                    '</div>' +
                    '<button type="button" class="ec-modal__close" data-ec-close aria-label="Close">✕</button>' +
                '</div>' +
                '<div class="ec-modal__body"></div>' +
                '<div class="ec-modal__foot"><button type="button" class="ec-btn" data-ec-close>Close</button></div>' +
            '</div>';
        document.body.appendChild(modal);

        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.closest('[data-ec-close]')) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) closeModal();
        });

        return modal;
    }

    function row(label, valueHtml) {
        return '<div class="ec-detail"><dt>' + label + '</dt><dd>' + valueHtml + '</dd></div>';
    }

    function openModal(ev) {
        var m = ensureModal();
        var s = span(ev);
        var multi = s.totalDays > 1;

        m.querySelector('.ec-modal__eyebrow').textContent = 'Event details';
        m.querySelector('.ec-modal__title').textContent = ev.title || 'Untitled event';
        m.querySelector('.ec-modal__body').onclick = null;

        var html = '';
        if (multi) {
            html += '<p class="ec-duration">Multi-day event · ' + s.totalDays + ' days</p>';
        }
        html += '<dl class="ec-details">';
        html += row('Starts', esc(fmtDay(s.start)) + '<span class="ec-time">' + esc(fmtTime(s.start)) + '</span>');
        html += row('Ends', esc(fmtDay(s.end)) + '<span class="ec-time">' + esc(fmtTime(s.end)) + '</span>');
        html += row('Venue', esc(ev.venue || 'Venue TBD'));
        if (ev.campus) html += row('Campus', esc(ev.campus));
        if (ev.university_wide !== undefined) {
            html += row('Open to', ev.university_wide ? 'All campuses' : 'This campus only');
        }
        if (ev.office) html += row('Organizer', esc(ev.office));
        if (ev.status_label) html += row('Status', esc(ev.status_label));
        html += '</dl>';
        html += '<div class="ec-description"><p class="ec-description__label">Description</p><p>' +
            (ev.description ? esc(ev.description) : '<span class="ec-muted">No description provided.</span>') + '</p></div>';
        if (Array.isArray(ev.documents) && ev.documents.length) {
            html += '<div class="ec-description"><p class="ec-description__label">Advisory Memo / Supporting Evidence</p><ul class="ec-documents">';
            ev.documents.forEach(function (document) {
                html += '<li><a href="' + esc(document.url) + '" target="_blank" rel="noopener">' + esc(document.label || 'View memo') + '</a></li>';
            });
            html += '</ul></div>';
        }

        m.querySelector('.ec-modal__body').innerHTML = html;

        if (m.hidden) lastFocus = document.activeElement;   // keep the original focus when coming from the day list
        m.hidden = false;
        document.body.classList.add('ec-no-scroll');
        m.querySelector('.ec-modal__close').focus();
    }

    /** Several events start on the clicked day: list them, click one for its details. */
    function openDayModal(key, events) {
        var m = ensureModal();
        var body = m.querySelector('.ec-modal__body');

        m.querySelector('.ec-modal__eyebrow').textContent = events.length + ' events';
        m.querySelector('.ec-modal__title').textContent = fmt(keyToDate(key), { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

        body.innerHTML = events.map(function (ev, i) {
            var s = span(ev);
            return '<button type="button" class="ec-item" data-ec-index="' + i + '">' +
                '<span class="ec-item__top">' +
                    '<span class="ec-item__title">' + esc(ev.title || 'Untitled event') + '</span>' +
                    '<span class="ec-item__venue">' + esc(ev.venue || 'Venue TBD') + '</span>' +
                '</span>' +
                '<span class="ec-item__when">' + esc(fmtTime(s.start) + (s.totalDays > 1 ? ' · ' + s.totalDays + ' days' : ' – ' + fmtTime(s.end))) + '</span>' +
                '<span class="ec-item__more">View details →</span>' +
            '</button>';
        }).join('');
        body.onclick = function (e) {
            var btn = e.target.closest('[data-ec-index]');
            if (btn) openModal(events[Number(btn.dataset.ecIndex)]);
        };

        if (m.hidden) lastFocus = document.activeElement;
        m.hidden = false;
        document.body.classList.add('ec-no-scroll');
        m.querySelector('.ec-modal__close').focus();
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('ec-no-scroll');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    // ---------- calendar ----------

    function init(opts) {
        // Accept a list or a keyed object (a sorted Laravel collection serialises as {"1": …, "0": …})
        var allEvents = Array.isArray(opts.events) ? opts.events : Object.values(opts.events || {});
        var office = 'All Offices';
        var selectedKey = null;                  // nothing selected until a date is clicked
        var selectedCell = null;
        var calendar;

        function filtered() {
            if (office === 'All Offices') return allEvents;
            return allEvents.filter(function (ev) { return ev.office_filter === office; });
        }

        // Events are drawn on their start date only: the bar is cut off at the end of
        // that day. The real end date/time is still shown in the details window.
        function toFc(ev) {
            var s = span(ev);
            var endOfStartDay = s.startKey + 'T23:59:59Z';
            var fcEvent = {
                title: ev.title || 'Untitled event',
                start: ev.start,
                end: s.totalDays > 1 ? endOfStartDay : (ev.end || ev.start),
                extendedProps: { raw: ev }
            };
            // Optional per-event look (e.g. Planning Office: pending / conflict / university-wide)
            if (ev.color) fcEvent.backgroundColor = fcEvent.borderColor = ev.color;
            if (ev.class_names) fcEvent.classNames = ev.class_names;
            return fcEvent;
        }

        // Same rule as the calendar: an event belongs to its start date
        function eventsStartingOn(key) {
            return filtered().filter(function (ev) {
                return ev.start && span(ev).startKey === key;
            });
        }

        // Panel before any date is clicked
        function renderPrompt() {
            if (opts.listLabelEl) opts.listLabelEl.textContent = 'Event details';
            if (opts.listEl) {
                opts.listEl.innerHTML = '<div class="ec-empty ec-prompt">' +
                    '<span class="ec-prompt__icon" aria-hidden="true">📅</span>' +
                    'Click a date on the calendar to see its events.' +
                '</div>';
            }
        }

        // "Events for <date>" panel
        function renderDay(key) {
            selectedKey = key;
            if (!opts.listEl) return;

            var matches = eventsStartingOn(key);

            if (opts.listLabelEl) {
                opts.listLabelEl.textContent = 'Events for ' + fmt(keyToDate(key), { month: 'long', day: 'numeric', year: 'numeric' });
            }

            if (!matches.length) {
                opts.listEl.innerHTML = '<div class="ec-empty">No events scheduled for this date.</div>';
                return;
            }

            opts.listEl.innerHTML = matches.map(function (ev, i) {
                var s = span(ev);
                var when = s.totalDays === 1
                    ? fmtTime(s.start) + ' – ' + fmtTime(s.end)
                    : s.totalDays + '-day event · ' + fmtTime(s.start) + ' → ' +
                      fmt(s.end, { weekday: 'short', month: 'short', day: 'numeric' }) + ', ' + fmtTime(s.end);

                return '<button type="button" class="ec-item" data-ec-index="' + i + '">' +
                    '<span class="ec-item__top">' +
                        '<span class="ec-item__title">' + esc(ev.title || 'Untitled event') + '</span>' +
                        '<span class="ec-item__venue">' + esc(ev.venue || 'Venue TBD') + '</span>' +
                    '</span>' +
                    '<span class="ec-item__when">' + esc(when) + '</span>' +
                    '<span class="ec-item__more">View details →</span>' +
                '</button>';
            }).join('');

            opts.listEl.onclick = function (e) {
                var btn = e.target.closest('[data-ec-index]');
                if (btn) openModal(matches[Number(btn.dataset.ecIndex)]);
            };
        }

        // PSU theme (blue / gold / white) from event-calendar.css, same on every page
        opts.calendarEl.classList.add('ec-calendar');

        calendar = new FullCalendar.Calendar(opts.calendarEl, Object.assign({
            timeZone: TZ,
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: filtered().map(toFc),
            showNonCurrentDates: false,          // hide the grayed-out days of the previous/next month
            fixedWeekCount: false,               // only as many week rows as the month needs
            eventDisplay: 'block',
            eventColor: '#4a5690',
            dayMaxEvents: 3,                     // "+N more" link when a day gets crowded
            height: 'auto',

            // Only the event name on the calendar; everything else is in the details window
            eventContent: function (arg) {
                if (arg.view.type.indexOf('dayGrid') !== 0) return true;   // default rendering in week/day views

                return {
                    html: '<div class="ec-card"><span class="ec-card__title">' + esc(arg.event.title) + '</span></div>'
                };
            },
            eventDidMount: function (info) {
                var ev = info.event.extendedProps.raw;
                info.el.setAttribute('title', (ev.title || 'Untitled event') + '\n' + whenText(ev) + (ev.venue ? '\n' + ev.venue : ''));
                info.el.classList.add('ec-event');
            },
            eventClick: function (info) {
                info.jsEvent.preventDefault();
                openModal(info.event.extendedProps.raw);
            },
            dateClick: function (info) {
                var key = info.dateStr.slice(0, 10);

                // No "Events for <date>" panel on this page: open the day's event(s) directly
                if (!opts.listEl) {
                    var dayEvents = eventsStartingOn(key);
                    if (dayEvents.length === 1) openModal(dayEvents[0]);
                    else if (dayEvents.length > 1) openDayModal(key, dayEvents);
                    return;
                }

                if (selectedCell) selectedCell.classList.remove('ec-day--selected');
                selectedCell = info.dayEl;
                selectedCell.classList.add('ec-day--selected');
                renderDay(info.dateStr.slice(0, 10));
            }
        }, opts.fcOptions || {}));

        if (opts.officeFilterEl) {
            opts.officeFilterEl.addEventListener('change', function () {
                office = this.value;
                calendar.removeAllEvents();
                calendar.addEventSource(filtered().map(toFc));
                if (selectedKey) renderDay(selectedKey);
            });
        }

        calendar.render();
        renderPrompt();

        window.addEventListener('resize', function () {
            requestAnimationFrame(function () { calendar.updateSize(); });
        });

        return calendar;
    }

    window.UniEventCalendar = { init: init, openDetails: openModal };
})();
