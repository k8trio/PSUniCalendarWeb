<?php

namespace App\Http\Controllers;

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class OfficeCalendarViewerController extends Controller
{
    public function show()
    {
        $viewer = Auth::user();
        $parentOffice = $this->parentOffice($viewer);
        $events = EventRequest::where('status', 'approved')
            ->where(function ($query) use ($viewer) {
                $query->where('is_university_wide', true)
                    ->orWhereHas('subOffices', fn ($subOffices) => $subOffices->where('users.id', $viewer->id));
            })
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
                'office' => $event->name,
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

        $invitations = $viewer->invitedEvents()
            ->where('status', 'approved')
            ->orderBy('start_datetime')
            ->get();

        return view('office.calendar-viewer', compact('viewer', 'parentOffice', 'events', 'invitations'));
    }

    public function eventDocument(EventRequest $event, int $documentIndex)
    {
        $viewer = Auth::user();
        abort_unless(
            $event->status === 'approved'
                && ($event->is_university_wide || $event->subOffices()->where('users.id', $viewer->id)->exists()),
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

    public function markNotificationsRead()
    {
        $viewer = Auth::user();
        $viewer->invitedEvents()->wherePivotNull('read_at')->updateExistingPivot(
            $viewer->invitedEvents()->wherePivotNull('read_at')->pluck('event_requests.id')->all(),
            ['read_at' => now()]
        );

        return back()->with('success', 'Meeting notifications marked as read.');
    }

    private function parentOffice(User $viewer): User
    {
        return $viewer->parentOffice()->where('role', 'office')->firstOrFail();
    }
}
