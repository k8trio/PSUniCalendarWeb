<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportTitle }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { color: #20243a; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        h1 { margin: 0 0 5px; color: #26336b; font-size: 20px; }
        .report-meta { margin-bottom: 20px; color: #626985; font-size: 10px; }
        .event { margin: 0 0 8px; border: 1px solid #dfe3ed; border-radius: 5px; }
        .event-heading { padding: 6px 9px; border-bottom: 1px solid #dfe3ed; background: #f3f5fa; page-break-after: avoid; }
        h2 { margin: 0; color: #26336b; font-size: 12px; }
        .event-number { float: right; color: #626985; font-size: 9px; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 7px; border-bottom: 1px solid #edf0f5; text-align: left; vertical-align: top; }
        tr { page-break-inside: avoid; }
        tr:last-child th, tr:last-child td { border-bottom: 0; }
        th { width: 23%; color: #626985; font-weight: bold; }
        td { white-space: pre-wrap; overflow-wrap: anywhere; }
        .empty { padding: 18px; border: 1px solid #dfe3ed; color: #626985; text-align: center; }
        .footer { margin-top: 18px; color: #7a8095; font-size: 8px; text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $reportTitle }}</h1>
    <p class="report-meta">
        @if($officeName)
            Office: {{ $officeName }} ·
        @endif
        Generated {{ now()->format('F j, Y g:i A') }} · {{ $events->count() }} event request(s)
    </p>

    @forelse($events as $index => $event)
        <section class="event">
            <header class="event-heading">
                <h2>{{ $event->title ?: 'Untitled event' }}</h2>
            </header>
            <table>
                <tbody>
                    <tr><th>Event title</th><td>{{ $event->title ?: '—' }}</td></tr>
                    <tr><th>Office</th><td>{{ $includeOfficeColumn ? $event->report_office_name : $officeName }}</td></tr>
                    <tr><th>Submitted by</th><td>{{ $event->name ?: '—' }}</td></tr>
                    <tr><th>For (Offices/Officials)</th><td>{{ $event->event_for ?: '—' }}</td></tr>
                    <tr><th>Attention (Persons/Units)</th><td>{{ $event->attention_to ?: '—' }}</td></tr>
                    <tr><th>Venue</th><td>{{ $event->venue_name ?: '—' }}</td></tr>
                    <tr><th>Campus</th><td>{{ $event->venueCampus() }}</td></tr>
                    <tr><th>Event scope</th><td>{{ \App\Services\PlanningOfficeDashboard::scopeLabel($event->scope()) }}</td></tr>
                    <tr><th>Start</th><td>{{ $event->start_datetime?->format('l, F j, Y · g:i A') ?: 'Not specified' }}</td></tr>
                    <tr><th>End</th><td>{{ $event->end_datetime?->format('l, F j, Y · g:i A') ?: 'Not specified' }}</td></tr>
                    <tr><th>Description</th><td>{{ $event->description ?: 'No event description provided.' }}</td></tr>
                    <tr><th>Status</th><td>{{ \App\Models\EventRequest::statusLabel($event->status) }}</td></tr>
                    <tr><th>Planning Office note</th><td>{{ $event->planning_note ?: '—' }}</td></tr>
                    <tr>
                        <th>Supporting documents</th>
                        <td>
                            @forelse($event->digital_documents ?? [] as $document)
                                {{ basename($document) }}@unless($loop->last), @endunless
                            @empty
                                None attached
                            @endforelse
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    @empty
        <p class="empty">No event requests were found.</p>
    @endforelse

    <p class="footer">UniCalendar · {{ now()->format('F j, Y') }}</p>
</body>
</html>
