<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EventRequest; // Adjust to match your model name
use App\Services\EventConflictChecker;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventApprovalController extends Controller
{
    public function approve(Request $request, $id, EventConflictChecker $conflictChecker)
    {
        $booking = EventRequest::findOrFail($id);
        $validated = $request->validate([
            'venue_name' => ['sometimes', 'required', 'string', 'max:255'],
            'campus' => ['sometimes', 'required', 'string', 'max:255'],
            'start_datetime' => ['sometimes', 'required', 'date'],
            'end_datetime' => ['sometimes', 'required', 'date', 'after_or_equal:start_datetime'],
            'is_university_wide' => ['sometimes', 'boolean'],
            'planning_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $proposal = [
            'venue_name' => $validated['venue_name'] ?? $booking->venue_name,
            'campus' => $validated['campus'] ?? $booking->campus,
            'start_datetime' => $validated['start_datetime'] ?? $booking->start_datetime,
            'end_datetime' => $validated['end_datetime'] ?? $booking->end_datetime,
        ];
        $conflicts = $conflictChecker->find(
            $proposal['venue_name'],
            $proposal['campus'],
            $proposal['start_datetime'],
            $proposal['end_datetime'],
            $booking->id
        );

        if ($conflicts->isNotEmpty()) {
            $conflictTitles = $conflicts->pluck('title')->implode(', ');

            return back()->withInput()->with('error', "This schedule conflicts with {$conflictTitles}. Adjust the venue or time before approving.");
        }

        $originalStatus = $booking->status;
        $booking->fill($proposal);
        $rescheduled = $booking->isDirty(['venue_name', 'campus', 'start_datetime', 'end_datetime']);
        if (array_key_exists('is_university_wide', $validated)) {
            $booking->is_university_wide = (bool) $validated['is_university_wide'];
        }
        if (array_key_exists('planning_note', $validated)) {
            $booking->planning_note = $validated['planning_note'];
        }
        $booking->read_at = null;   // the office gets notified of the decision

        try {
            if (config('services.google.service_account_json') && config('services.google.calendar_id')) {
                $client = new Client();
                $client->setAuthConfig(config('services.google.service_account_json'));
                $client->addScope(Calendar::CALENDAR);

                $service = new Calendar($client);

                $event = new Event([
                    'summary' => $booking->title,
                    'location' => $booking->venue_name,
                    'description' => $booking->description,
                    'start' => [
                        'dateTime' => date('c', strtotime($booking->start_datetime)),
                        'timeZone' => 'Asia/Manila',
                    ],
                    'end' => [
                        'dateTime' => date('c', strtotime($booking->end_datetime)),
                        'timeZone' => 'Asia/Manila',
                    ],
                ]);

                $googleEvent = $service->events->insert(config('services.google.calendar_id'), $event);

                $booking->status = 'approved';
                $booking->google_event_id = $googleEvent->getId();
                $booking->save();
                $this->logApproval($booking, $originalStatus, $rescheduled);

                return back()->with('success', 'Event approved and added to Google Calendar.');
            }

            $booking->status = 'approved';
            $booking->save();
            $this->logApproval($booking, $originalStatus, $rescheduled);

            return back()->with('success', 'Event approved successfully.');
        } catch (\Throwable $e) {
            $booking->status = 'approved';
            $booking->save();
            $this->logApproval($booking, $originalStatus, $rescheduled);

            return back()->with('error', 'Event approved locally, but Google Calendar sync could not be completed.');
        }
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'planning_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $booking = EventRequest::findOrFail($id);
        $originalStatus = $booking->status;
        $booking->update([
            'status' => 'rejected',
            'planning_note' => $validated['planning_note'] ?? $booking->planning_note,
            'read_at' => null,
        ]);

        if ($originalStatus === 'conflict') {
            $this->log('conflict_resolved', "Resolved the conflict on \"{$booking->title}\" by rejecting it");
        } else {
            $this->log('rejected', "Rejected \"{$booking->title}\" from {$booking->name}");
        }

        return back()->with('success', 'Event request rejected.');
    }

    /**
     * Send a pending request back to the office for revision. The office can edit and
     * resubmit it; the planning note tells them what to change.
     */
    public function returnForRevision(Request $request, $id)
    {
        $validated = $request->validate([
            'planning_note' => ['required', 'string', 'max:2000'],
        ], [
            'planning_note.required' => 'Add a planning note so the office knows what to revise.',
        ]);
        $booking = EventRequest::findOrFail($id);

        if (! in_array($booking->status, EventRequest::REVIEW_STATUSES, true)) {
            return back()->with('error', 'Only requests awaiting review can be returned for revision.');
        }

        $booking->update([
            'status' => 'returned',
            'planning_note' => $validated['planning_note'],
            'read_at' => null,
        ]);
        $this->log('returned', "Returned \"{$booking->title}\" to {$booking->name} for revision");

        return back()->with('success', 'Request returned to the office for revision.');
    }

    private function logApproval(EventRequest $booking, string $originalStatus, bool $rescheduled): void
    {
        if ($originalStatus === 'conflict') {
            $this->log('conflict_resolved', "Resolved the conflict on \"{$booking->title}\" and approved it" . ($rescheduled ? ' with a new schedule' : ''));
        } elseif ($rescheduled) {
            $this->log('rescheduled', "Rescheduled and approved \"{$booking->title}\" ({$booking->venue_name}, {$booking->start_datetime->format('M j, Y g:i A')})");
        } else {
            $this->log('approved', "Approved \"{$booking->title}\" from {$booking->name}");
        }
    }

    private function log(string $action, string $description): void
    {
        ActivityLog::record(Auth::user()->email, $action, $description);
    }
}