<?php

namespace App\Http\Controllers;

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class OfficeCalendarViewerController extends Controller
{
    public function show()
    {
        $viewer = Auth::user();
        $parentOffice = $this->parentOffice($viewer);
        $events = EventRequest::where('status', 'approved')
            ->where(fn ($query) => $query
                ->where('email', $parentOffice->email)
                ->orWhere('is_university_wide', true))
            ->orderBy('start_datetime')
            ->get()
            ->map(fn (EventRequest $event) => [
                'title' => $event->title,
                'start' => $event->start_datetime,
                'end' => $event->end_datetime,
                'description' => $event->description,
                'venue' => $event->venue_name,
                'campus' => $event->venueCampus(),
                'university_wide' => (bool) $event->is_university_wide,
                'office' => $parentOffice->name,
                'documents' => collect($event->digital_documents ?? [])
                    ->keys()
                    ->map(fn ($index) => [
                        'label' => 'Advisory Memo' . ($index > 0 ? ' ' . ($index + 1) : ''),
                        'url' => route('office.viewer-calendar.documents.show', [
                            'event' => $event->id,
                            'documentIndex' => $index + 1,
                        ]),
                    ])
                    ->all(),
            ])
            ->values();

        return view('office.calendar-viewer', compact('viewer', 'parentOffice', 'events'));
    }

    public function eventDocument(EventRequest $event, int $documentIndex)
    {
        $parentOffice = $this->parentOffice(Auth::user());
        abort_unless(
            $event->status === 'approved'
                && ($event->email === $parentOffice->email || $event->is_university_wide),
            404
        );

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

    private function parentOffice(User $viewer): User
    {
        return $viewer->parentOffice()
            ->where('role', 'office')
            ->firstOrFail();
    }
}
