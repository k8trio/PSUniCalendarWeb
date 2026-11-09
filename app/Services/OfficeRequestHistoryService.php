<?php

namespace App\Services;

use App\Models\EventRequest;
use App\Models\OfficeRequestHistory;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class OfficeRequestHistoryService
{
    private const TRACKED_FIELDS = [
        'name',
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
        'digital_documents',
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

    public function record(User $user, EventRequest $event, string $action, ?array $before, array $after): void
    {
        OfficeRequestHistory::where('office_email', $user->email)
            ->where('is_undone', true)
            ->delete();

        OfficeRequestHistory::create([
            'office_email' => $user->email,
            'event_request_id' => $event->id,
            'action' => $action,
            'before_data' => $before,
            'after_data' => $after,
        ]);
    }

    public function controlsFor(User $user): array
    {
        $undo = OfficeRequestHistory::where('office_email', $user->email)
            ->where('is_undone', false)
            ->latest('id')
            ->first();
        $redo = OfficeRequestHistory::where('office_email', $user->email)
            ->where('is_undone', true)
            ->oldest('id')
            ->first();

        return [
            'canUndoRequest' => $undo !== null && $this->canUndo($undo, $user),
            'canRedoRequest' => $redo !== null && $this->canRedo($redo, $user),
        ];
    }

    public function undo(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $history = OfficeRequestHistory::where('office_email', $user->email)
                ->where('is_undone', false)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $history) {
                throw new DomainException('There is no Office request action to undo.');
            }

            $event = EventRequest::where('email', $user->email)
                ->lockForUpdate()
                ->find($history->event_request_id);

            if (! $event || ! $this->canUndo($history, $user, $event)) {
                throw new DomainException('This request has changed since your last action, so it cannot be undone.');
            }

            if ($history->action === 'created') {
                $event->update(['status' => 'cancelled']);
            } else {
                $this->restore($event, $history->before_data);
            }

            $history->update(['is_undone' => true]);
        });
    }

    public function redo(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $history = OfficeRequestHistory::where('office_email', $user->email)
                ->where('is_undone', true)
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if (! $history) {
                throw new DomainException('There is no Office request action to redo.');
            }

            $event = EventRequest::where('email', $user->email)
                ->lockForUpdate()
                ->find($history->event_request_id);

            if (! $event || ! $this->canRedo($history, $user, $event)) {
                throw new DomainException('This request has changed since it was undone, so it cannot be redone.');
            }

            $this->restore($event, $history->after_data);
            $history->update(['is_undone' => false]);
        });
    }

    private function canUndo(OfficeRequestHistory $history, User $user, ?EventRequest $event = null): bool
    {
        $event ??= EventRequest::where('email', $user->email)->find($history->event_request_id);

        return $event !== null
            && $history->office_email === $user->email
            && $this->snapshotsMatch($this->snapshot($event), $history->after_data);
    }

    private function canRedo(OfficeRequestHistory $history, User $user, ?EventRequest $event = null): bool
    {
        $event ??= EventRequest::where('email', $user->email)->find($history->event_request_id);
        if (! $event || $history->office_email !== $user->email) {
            return false;
        }

        $expected = $history->action === 'created'
            ? array_replace($history->after_data, ['status' => 'cancelled'])
            : $history->before_data;

        return is_array($expected) && $this->snapshotsMatch($this->snapshot($event), $expected);
    }

    private function snapshotsMatch(array $actual, array $expected): bool
    {
        ksort($actual);
        ksort($expected);

        return $actual === $expected;
    }

    private function restore(EventRequest $event, array $data): void
    {
        $event->forceFill($data)->save();
    }
}
