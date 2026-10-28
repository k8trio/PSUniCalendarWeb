<?php

namespace App\Http\Controllers;

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index()
    {
        $events = EventRequest::where('status', 'approved')
            ->orderBy('start_datetime', 'asc')
            ->get()
            ->map(function ($event) {
                $campus = $event->campus ?: (\App\Models\User::campusFromText($event->venue_name) ?? 'All Campus');

                return [
                    'title' => $event->title,
                    'start' => $event->start_datetime,
                    'end' => $event->end_datetime,
                    'description' => $event->description,
                    'venue' => $event->venue_name,
                    'campus' => $campus,
                    'university_wide' => (bool) $event->is_university_wide,
                    'office' => $event->name,
                    'office_filter' => User::UNIVERSITY_OFFICE_ACCOUNT_LABELS[$event->email] ?? 'Other offices',
                    'documents' => collect($event->digital_documents ?? [])
                        ->keys()
                        ->map(fn ($index) => [
                            'label' => 'Advisory Memo' . ($index > 0 ? ' ' . ($index + 1) : ''),
                            'url' => route('user.events.documents.show', [
                                'event' => $event->id,
                                'documentIndex' => $index + 1,
                            ]),
                        ])
                        ->all(),
                ];
            })
            ->values();

        return view('user.calendar', compact('events'));
    }

    public function eventDocument(EventRequest $event, int $documentIndex)
    {
        abort_unless($event->status === 'approved', 404);

        $documents = $event->digital_documents ?? [];
        abort_unless($documentIndex >= 1 && isset($documents[$documentIndex - 1]), 404);

        $path = $documents[$documentIndex - 1];
        abort_unless(
            is_string($path)
                && str_starts_with($path, 'event-documents/')
                && basename($path) === substr($path, strlen('event-documents/')),
            404
        );
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, basename($path), [], 'inline');
    }
}
