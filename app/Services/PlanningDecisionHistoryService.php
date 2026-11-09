<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\EventRequest;
use App\Models\PlanningDecisionHistory;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlanningDecisionHistoryService
{
    private const TRACKED_FIELDS = [
        'name',
        'email',
        'title',
        'venue_name',
        'event_for',
        'attention_to',
        'campus',
        'is_university_wide',
        'description',
        'start_datetime',
        'end_datetime',
        'status',
        'planning_note',
        'google_event_id',
        'digital_documents',
        'read_at',
    ];

    public function snapshot(EventRequest $event): array
    {
        $snapshot = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $value = $event->getAttribute($field);
            $snapshot[$field] = $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : $value;
        }

        ksort($snapshot);

        return $snapshot;
    }

    public function record(User $user, EventRequest $event, string $decision, array $before, array $after): void
    {
        PlanningDecisionHistory::where('planning_email', $user->email)
            ->where('is_undone', true)
            ->delete();

        PlanningDecisionHistory::create([
            'planning_email' => $user->email,
            'event_request_id' => $event->id,
            'decision' => $decision,
            'before_data' => $before,
            'after_data' => $after,
        ]);
    }

    public function controlsFor(User $user): array
    {
        $undo = PlanningDecisionHistory::where('planning_email', $user->email)
            ->where('is_undone', false)
            ->latest('id')
            ->first();
        $redo = PlanningDecisionHistory::where('planning_email', $user->email)
            ->where('is_undone', true)
            ->oldest('id')
            ->first();

        return [
            'canUndoDecision' => $undo !== null && $this->matches($undo, $user, $undo->after_data),
            'canRedoDecision' => $redo !== null && $this->matches($redo, $user, $redo->before_data),
        ];
    }

    public function undo(User $user, GoogleCalendarEventService $calendar): void
    {
        DB::transaction(function () use ($user, $calendar): void {
            $history = PlanningDecisionHistory::where('planning_email', $user->email)
                ->where('is_undone', false)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $history) {
                throw new DomainException('There is no Planning Office decision to undo.');
            }

            $event = EventRequest::lockForUpdate()->find($history->event_request_id);
            if (! $event || ! $this->matches($history, $user, $history->after_data, $event)) {
                throw new DomainException('This event changed after the decision, so it cannot be undone safely.');
            }

            if ($history->decision === 'approved' && $history->after_data['google_event_id']) {
                $calendar->deleteForHistory($history->after_data['google_event_id'], $event->id);
            }

            $this->restore($event, $history->before_data);
            $history->update(['is_undone' => true]);
            ActivityLog::record($user->email, 'decision_undone', "Undid the {$history->decision} decision for \"{$event->title}\"");
        });
    }

    public function redo(User $user, GoogleCalendarEventService $calendar): void
    {
        DB::transaction(function () use ($user, $calendar): void {
            $history = PlanningDecisionHistory::where('planning_email', $user->email)
                ->where('is_undone', true)
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if (! $history) {
                throw new DomainException('There is no Planning Office decision to redo.');
            }

            $event = EventRequest::lockForUpdate()->find($history->event_request_id);
            if (! $event || ! $this->matches($history, $user, $history->before_data, $event)) {
                throw new DomainException('This event changed after the decision was undone, so it cannot be redone safely.');
            }

            $after = $history->after_data;
            if ($history->decision === 'approved' && $after['google_event_id']) {
                $event->forceFill($after);
                $after['google_event_id'] = $calendar->insertForHistory($event);
            }

            $this->restore($event, $after);
            $history->update([
                'after_data' => $after,
                'is_undone' => false,
            ]);
            ActivityLog::record($user->email, 'decision_redone', "Redid the {$history->decision} decision for \"{$event->title}\"");
        });
    }

    private function matches(
        PlanningDecisionHistory $history,
        User $user,
        array $expected,
        ?EventRequest $event = null
    ): bool {
        $event ??= EventRequest::find($history->event_request_id);
        if (! $event || $history->planning_email !== $user->email) {
            return false;
        }

        return $this->snapshot($event) === $this->sortSnapshot($expected);
    }

    private function sortSnapshot(array $snapshot): array
    {
        ksort($snapshot);

        return $snapshot;
    }

    private function restore(EventRequest $event, array $data): void
    {
        $event->forceFill($data)->save();
    }
}
