<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportTitle }}</title>
    <style>
        @page { margin: 192px 52px 90px; }
        body {
            color: #20243a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.45;
        }
        .report-letterhead {
            position: fixed;
            top: -205px;
            left: -52px;
            right: -52px;
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
            right: -52px;
            bottom: -90px;
            left: -52px;
            height: 86px;
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
            bottom: 62px;
            width: 328.8pt;
            height: 19.55pt;
        }
        h1 {
            margin: 0 0 5px;
            color: #26336b;
            font-size: 19px;
        }
        .report-meta {
            margin-bottom: 16px;
            color: #626985;
        }
        .event {
            margin-bottom: 14px;
            padding: 10px;
            border: 1px solid #dfe3ed;
            page-break-inside: avoid;
        }
        .event-title {
            margin: 0 0 8px;
            color: #26336b;
            font-size: 14px;
        }
        .field {
            margin: 4px 0;
        }
        .label {
            color: #626985;
            font-weight: bold;
        }
        .memo {
            margin-top: 8px;
            padding-top: 7px;
            border-top: 1px solid #dfe3ed;
            white-space: pre-wrap;
        }
        a {
            color: #174ea6;
            overflow-wrap: anywhere;
        }
        .empty {
            padding: 18px;
            border: 1px solid #dfe3ed;
            color: #626985;
            text-align: center;
        }
        .footer {
            margin-top: 18px;
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

    @forelse($events as $event)
        <article class="event">
            <h2 class="event-title">{{ $event->title ?: 'Untitled event' }}</h2>

            <p class="field">
                <span class="label">Office Advisory No.:</span>
                {{ $event->office_advisory_no ?: '—' }}
            </p>
            <p class="field">
                <span class="label">Series:</span>
                {{ $event->series_text ?: '—' }}
            </p>
            <p class="field">
                <span class="label">Date:</span>
                {{ $event->start_datetime?->format('F j, Y') ?: 'Not specified' }}
                @if($event->end_datetime && $event->end_datetime->format('Y-m-d') !== $event->start_datetime?->format('Y-m-d'))
                    – {{ $event->end_datetime->format('F j, Y') }}
                @endif
            </p>
            <p class="field">
                <span class="label">Subject:</span>
                {{ $event->title ?: '—' }}
            </p>

            <div class="memo">
                <span class="label">Memo:</span>
                <div>{{ $event->description ?: 'No memo provided.' }}</div>

                <p class="label">Memo attachments:</p>
                @forelse($event->digital_documents ?? [] as $document)
                    @php
                        $validDocument = is_string($document)
                            && str_starts_with($document, 'event-documents/')
                            && basename($document) === substr($document, strlen('event-documents/'))
                            && \Illuminate\Support\Facades\Storage::disk('public')->exists($document);
                    @endphp

                    @if($validDocument)
                        <a href="{{ route('planning_office.events.documents.show', ['event' => $event, 'documentIndex' => $loop->iteration]) }}">
                            {{ basename($document) }}
                        </a><br>
                    @else
                        {{ is_string($document) ? 'Attachment unavailable: ' . basename($document) : 'Invalid attachment' }}<br>
                    @endif
                @empty
                    No attachments
                @endforelse
            </div>
        </article>
    @empty
        <p class="empty">No events added directly by the Planning Office were found.</p>
    @endforelse

    <p class="footer">UniCalendar · {{ now()->format('F j, Y') }}</p>
</body>
</html>