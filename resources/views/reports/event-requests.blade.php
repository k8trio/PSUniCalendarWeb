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
        .report-meta { margin-bottom: 18px; color: #626985; font-size: 10px; }
        .event-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .event-table thead { display: table-header-group; }
        .event-table th, .event-table td { padding: 7px 8px; border: 1px solid #dfe3ed; text-align: left; vertical-align: top; }
        .event-table th { background: #f3f5fa; color: #26336b; font-weight: bold; }
        .event-table tr { page-break-inside: avoid; }
        .event-table tbody tr:nth-child(even) { background: #fafbfe; }
        .event-number { color: #626985; white-space: nowrap; }
        .subject-title { color: #20243a; font-weight: bold; }
        .subject-detail { display: block; margin-top: 3px; color: #626985; font-size: 9px; }
        .advisory-link { display: block; color: #174ea6; text-decoration: underline; overflow-wrap: anywhere; }
        .advisory-link + .advisory-link { margin-top: 4px; }
        .advisory-unavailable { display: block; color: #626985; overflow-wrap: anywhere; }
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

    @if($events->isEmpty())
        <p class="empty">No event requests were found.</p>
    @else
        <table class="event-table">
            <thead>
                <tr>
                    <th style="width: 10%">Event No.</th>
                    <th style="width: 17%">Date</th>
                    <th style="width: 48%">Subject</th>
                    <th style="width: 25%">Advisory</th>
                </tr>
            </thead>
            <tbody>
                @foreach($events as $event)
                    <tr>
                        <td class="event-number">#{{ $event->id }}</td>
                        <td>
                            {{ $event->start_datetime?->format('F j, Y') ?: 'Not specified' }}
                            @if($event->end_datetime && $event->end_datetime->format('Y-m-d') !== $event->start_datetime?->format('Y-m-d'))
                                <br>to {{ $event->end_datetime->format('F j, Y') }}
                            @endif
                        </td>
                        <td>
                            <span class="subject-title">{{ $event->title ?: 'Untitled event' }}</span>
                            <span class="subject-detail">
                                {{ $includeOfficeColumn ? ($event->report_office_name ?: $event->name ?: 'Unknown office') : ($officeName ?: $event->name ?: 'Unknown office') }}
                                · Submitted by {{ $event->name ?: 'Not specified' }}
                                · {{ $event->venue_name ?: 'Venue not specified' }}
                            </span>
                        </td>
                        <td>
                            @forelse($event->digital_documents ?? [] as $document)
                                @php
                                    $isEventDocument = is_string($document)
                                        && str_starts_with($document, 'event-documents/')
                                        && basename($document) === substr($document, strlen('event-documents/'));
                                    $documentExists = $isEventDocument
                                        && \Illuminate\Support\Facades\Storage::disk('public')->exists($document);
                                @endphp
                                @if($documentExists)
                                    <a
                                        class="advisory-link"
                                        href="{{ route($advisoryDocumentRouteName, ['event' => $event, 'documentIndex' => $loop->iteration]) }}"
                                    >View advisory memo {{ count($event->digital_documents) > 1 ? $loop->iteration : '' }}</a>
                                @else
                                    <span class="advisory-unavailable">
                                        {{ is_string($document) ? 'Attachment unavailable: ' . basename($document) : 'Invalid advisory attachment' }}
                                    </span>
                                @endif
                            @empty
                                None attached
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">UniCalendar · {{ now()->format('F j, Y') }}</p>
</body>
</html>
