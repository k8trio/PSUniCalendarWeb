<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportTitle }}</title>
    <style>
        @page {
            margin: 192px 36px 82px;
        }

        body {
            color: #20243a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            line-height: 1.4;
        }

        .report-letterhead {
            position: fixed;
            top: -205px;
            left: -36px;
            right: -36px;
            height: 128.1pt;
            background: #ffff00;
        }

        .report-letterhead__seal {
            position: absolute;
            top: 21pt;
            left: 38pt;
            width: 90pt;
            height: 90pt;
        }

        .report-letterhead__identity {
            position: absolute;
            top: 0;
            right: 96pt;
            left: 96pt;
            color: #111;
            text-align: center;
            font-family: ReportTemplateTimes, "Times New Roman", serif;
        }

        .report-letterhead__identity span,
        .report-letterhead__identity strong {
            position: absolute;
            right: 0;
            left: 0;
            display: block;
            font-weight: normal;
        }

        .report-letterhead__identity .report-letterhead__republic {
            top: 20pt;
            font-size: 11pt;
        }

        .report-letterhead__identity strong {
            top: 34pt;
            font-family: ReportTemplateOldEnglish, "Old English Text MT", serif;
            font-size: 24pt;
            line-height: 1;
        }

        .report-letterhead__identity .report-letterhead__campus {
            top: 62pt;
            font-size: 11pt;
        }

        .report-letterhead__identity .report-letterhead__address {
            top: 75pt;
            font-size: 11pt;
        }

        .report-letterhead__identity .report-letterhead__contacts {
            top: 99pt;
            font-family: Arial, sans-serif;
            font-size: 7pt;
            white-space: nowrap;
        }

        .report-letterhead__contacts img {
            width: 10.8pt;
            height: 10.8pt;
            vertical-align: middle;
        }

        .report-letterhead__bagong {
            position: absolute;
            top: 13.8pt;
            right: 44.7pt;
            width: 101.1pt;
            height: 95pt;
        }

        .report-letterhead__rule {
            position: absolute;
            top: 90pt;
            right: 18pt;
            left: 18pt;
            height: 0.75pt;
            background: #fff;
        }

        .report-letterfoot {
            position: fixed;
            right: -36px;
            bottom: -82px;
            left: -36px;
            height: 78px;
        }

        .report-letterfoot__banner {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: auto;
        }

        .report-letterfoot__logos {
            position: absolute;
            right: 31.2pt;
            bottom: 58px;
            width: 328.8pt;
            height: 19.55pt;
        }

        h1 {
            margin: 0 0 5px;
            color: #26336b;
            font-size: 18px;
        }

        .report-meta {
            margin: 0 0 14px;
            color: #626985;
            font-size: 9px;
        }

        .event-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .event-table thead {
            display: table-header-group;
        }

        .event-table th,
        .event-table td {
            padding: 7px;
            border: 1px solid #d5dbea;
            text-align: left;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        .event-table th {
            background: #26336b;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
        }

        .event-table tr {
            page-break-inside: avoid;
        }

        .event-table tbody tr:nth-child(even) {
            background: #f5f7fb;
        }

        .advisory-number {
            font-weight: bold;
        }

        .subject {
            font-weight: bold;
        }

        .posted-time {
            display: block;
            margin-top: 2px;
            color: #626985;
            font-size: 8px;
        }

        .memo-link {
            color: #174ea6;
            text-decoration: underline;
            overflow-wrap: anywhere;
        }

        .memo-link + .memo-link {
            display: block;
            margin-top: 3px;
        }

        .memo-unavailable,
        .empty {
            color: #626985;
        }

        .empty {
            padding: 12px;
            border: 1px solid #d5dbea;
            text-align: center;
        }

        .footer {
            margin-top: 14px;
            color: #7a8095;
            font-size: 8px;
            text-align: right;
        }
    </style>
</head>
<body>
    @include('reports.partials.letterhead')

    <h1>{{ $reportTitle }}</h1>
    <p class="report-meta">
        Generated {{ now()->format('F j, Y g:i A') }} · {{ $events->count() }} event(s)
    </p>

    @if($events->isEmpty())
        <p class="empty">No events added directly by the Planning Office were found.</p>
    @else
        <table class="event-table">
            <thead>
                <tr>
                    <th style="width: 17%">Office Advisory No.</th>
                    <th style="width: 14%">Series</th>
                    <th style="width: 25%">Subject</th>
                    <th style="width: 17%">When Posted</th>
                    <th style="width: 27%">Advisory Memo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($events as $event)
                    <tr>
                        <td class="advisory-number">
                            {{ $event->office_advisory_no ?: '—' }}
                        </td>
                        <td>
                            {{ $event->series_text ?: '—' }}
                        </td>
                        <td class="subject">
                            {{ $event->title ?: 'Untitled event' }}
                        </td>
                        <td>
                            {{ $event->created_at?->format('M j, Y') ?: '—' }}
                            @if($event->created_at)
                                <span class="posted-time">{{ $event->created_at->format('g:i A') }}</span>
                            @endif
                        </td>
                        <td>
                            @forelse($event->digital_documents ?? [] as $document)
                                @php
                                    $validDocument = is_string($document)
                                        && str_starts_with($document, 'event-documents/')
                                        && basename($document) === substr($document, strlen('event-documents/'))
                                        && \Illuminate\Support\Facades\Storage::disk('public')->exists($document);
                                @endphp

                                @if($validDocument)
                                    <a
                                        class="memo-link"
                                        href="{{ route('planning_office.events.documents.show', ['event' => $event, 'documentIndex' => $loop->iteration]) }}"
                                    >{{ basename($document) }}</a>
                                @else
                                    <span class="memo-unavailable">Attachment unavailable</span>
                                @endif
                            @empty
                                <span class="memo-unavailable">No memo attached</span>
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