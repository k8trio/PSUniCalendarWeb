<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportTitle }}</title>
    <style>
        @page { margin: 192px 72px 90px; }
        body { color: #20243a; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        .report-letterhead { position: fixed; top: -205px; left: -72px; right: -72px; height: 128.1pt; background: #ffff00; }
        .report-letterhead__seal { position: absolute; top: 21pt; left: 38pt; width: 90pt; height: 90pt; }
        .report-letterhead__identity { position: absolute; top: 0; right: 96pt; left: 96pt; color: #111; text-align: center; font-family: ReportTemplateTimes, "Times New Roman", serif; }
        .report-letterhead__identity span, .report-letterhead__identity strong { position: absolute; right: 0; left: 0; display: block; font-weight: normal; }
        .report-letterhead__identity .report-letterhead__republic { top: 20pt; font-size: 11pt; }
        .report-letterhead__identity strong { top: 34pt; font-family: ReportTemplateOldEnglish, "Old English Text MT", serif; font-size: 24pt; line-height: 1; }
        .report-letterhead__identity .report-letterhead__campus { top: 62pt; font-size: 11pt; }
        .report-letterhead__identity .report-letterhead__address { top: 75pt; font-size: 11pt; }
        .report-letterhead__identity .report-letterhead__contacts { top: 99pt; font-family: Arial, sans-serif; font-size: 7pt; white-space: nowrap; }
        .report-letterhead__contacts img { width: 10.8pt; height: 10.8pt; vertical-align: middle; }
        .report-letterhead__bagong { position: absolute; top: 13.8pt; right: 44.7pt; width: 101.1pt; height: 95pt; }
        .report-letterhead__rule { position: absolute; top: 90pt; right: 18pt; left: 18pt; height: 0.75pt; background: #fff; }
        .report-letterfoot { position: fixed; right: -72px; bottom: -90px; left: -72px; height: 86px; }
        .report-letterfoot__banner { position: absolute; bottom: 0; left: 0; width: 100%; height: auto; }
        .report-letterfoot__logos { position: absolute; right: 31.2pt; bottom: 62px; width: 328.8pt; height: 19.55pt; }
        h1 { margin: 0 0 5px; color: #26336b; font-size: 20px; }
        .report-meta { margin-bottom: 20px; color: #626985; font-size: 10px; }
        .event { margin: 0 0 4px; border: 1px solid #dfe3ed; border-radius: 5px; }
        .event-heading { padding: 6px 9px; border-bottom: 1px solid #dfe3ed; background: #f3f5fa; page-break-after: avoid; }
        h2 { margin: 0; color: #26336b; font-size: 12px; }
        .event-number { float: right; color: #626985; font-size: 9px; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 7px; border-bottom: 1px solid #edf0f5; text-align: left; vertical-align: top; }
        tr { page-break-inside: avoid; }
        tr:last-child th, tr:last-child td { border-bottom: 0; }
        th { width: 23%; color: #626985; font-weight: bold; }
        td { white-space: pre-wrap; overflow-wrap: anywhere; }
        .event--compact td { white-space: normal; }
        .empty { padding: 18px; border: 1px solid #dfe3ed; color: #626985; text-align: center; }
        .footer { margin-top: 18px; color: #7a8095; font-size: 8px; text-align: right; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead')
    <h1>{{ $reportTitle }}</h1>
    <p class="report-meta">
        @if($officeName)
            Office: {{ $officeName }} ·
        @endif
        @if(!empty($reportFilterLabel))
            Included: {{ $reportFilterLabel }} ·
        @endif
        Generated {{ now()->format('F j, Y g:i A') }} · {{ $events->count() }} event request(s)
    </p>

    @forelse($events as $index => $event)
        <section class="event {{ $showPostEndDetails ? '' : 'event--compact' }}">
            <header class="event-heading">
                <h2>{{ $event->title ?: 'Untitled event' }}</h2>
            </header>
            <table>
                <tbody>
                    <tr><th>Event title</th><td>{{ $event->title ?: '—' }}</td></tr>
                    <tr><th>Venue</th><td>{{ $event->venue_name ?: '—' }}</td></tr>
                    @unless($showPostEndDetails)
                        <tr><th>Submitted by</th><td>{{ $event->name ?: '—' }}</td></tr>
                        <tr>
                            <th>Memo</th>
                            <td>{{ count($event->digital_documents ?? []) > 0 ? 'Attached memo' : 'None' }}</td>
                        </tr>
                    @endunless
                    @if($showPostEndDetails)
                        <tr><th>Office</th><td>{{ $includeOfficeColumn ? $event->report_office_name : $officeName }}</td></tr>
                        <tr><th>Submitted by</th><td>{{ $event->name ?: '—' }}</td></tr>
                        <tr><th>For (Offices/Officials)</th><td>{{ $event->event_for ?: '—' }}</td></tr>
                        <tr><th>Attention (Persons/Units)</th><td>{{ $event->attention_to ?: '—' }}</td></tr>
                        <tr><th>Campus</th><td>{{ $event->venueCampus() }}</td></tr>
                        <tr><th>Event scope</th><td>{{ \App\Services\PlanningOfficeDashboard::scopeLabel($event->scope()) }}</td></tr>
                    @endif
                    @unless($showPostEndDetails)
                        <tr>
                            <th>Event date</th>
                            <td>{{ ($event->start_datetime?->format('F j, Y') ?: 'Not specified') . ' – ' . ($event->end_datetime?->format('F j, Y') ?: 'Not specified') }}</td>
                        </tr>
                    @else
                        <tr><th>Start</th><td>{{ $event->start_datetime?->format('l, F j, Y · g:i A') ?: 'Not specified' }}</td></tr>
                        <tr><th>End</th><td>{{ $event->end_datetime?->format('l, F j, Y · g:i A') ?: 'Not specified' }}</td></tr>
                    @endunless
                    @if($showPostEndDetails)
                        <tr><th>Description</th><td>{{ $event->description ?: 'No event description provided.' }}</td></tr>
                        <tr><th>Status</th><td>{{ \App\Models\EventRequest::statusLabel($event->status) }}</td></tr>
                        <tr><th>Planning Office note</th><td>{{ $event->planning_note ?: '—' }}</td></tr>
                    @endif
                    @if($showPostEndDetails)
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
                    @endif
                </tbody>
            </table>
        </section>
    @empty
        <p class="empty">No event requests were found.</p>
    @endforelse

    <p class="footer">UniCalendar · {{ now()->format('F j, Y') }}</p>
</body>
</html>
