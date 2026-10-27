<?php

namespace App\Services;

use App\Models\EventRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use DateTimeInterface;

class EventConflictChecker
{
    /** Statuses that hold a venue (block other requests from the same slot). */
    public const BLOCKING_STATUSES = ['approved', 'pending', 'conflict'];
    public const OFFICE_OPEN = '08:00:00';
    public const OFFICE_CLOSE = '17:00:00';

    public static function isWithinOfficeHours(Carbon $start, Carbon $end): bool
    {
        return $start->format('H:i:s') >= self::OFFICE_OPEN
            && $start->format('H:i:s') < self::OFFICE_CLOSE
            && $end->format('H:i:s') >= self::OFFICE_OPEN
            && $end->format('H:i:s') <= self::OFFICE_CLOSE;
    }

    public function find(string $venue, ?string $campus, string|DateTimeInterface $start, string|DateTimeInterface $end, ?int $excludeId = null): Collection
    {
        return EventRequest::query()
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->whereRaw('LOWER(TRIM(venue_name)) = ?', [mb_strtolower(trim($venue))])
            ->where('start_datetime', '<', $end)
            ->where('end_datetime', '>', $start)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->get()
            ->filter(function (EventRequest $event) use ($campus) {
                return !$campus
                    || !$event->campus
                    || $campus === 'All Campus'
                    || $event->campus === 'All Campus'
                    || $event->campus === $campus;
            })
            ->values();
    }

    /**
     * Earliest free slot on a later date during office hours with the same duration
     * as the requested one. Returns [start, end] or null if none is free.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function nextAvailableSlot(string $venue, ?string $campus, DateTimeInterface $start, DateTimeInterface $end, ?int $excludeId = null, int $searchDays = 14): ?array
    {
        $start = Carbon::instance($start);
        $end = Carbon::instance($end);
        $duration = $end->getTimestamp() - $start->getTimestamp();
        $firstDate = $start->copy()->startOfDay()->addDay();
        $lastDate = $firstDate->copy()->addDays($searchDays - 1);
        $searchUntil = $lastDate->copy()->setTime(17, 0)->addSeconds($duration);

        $busy = $this->find($venue, $campus, $start, $searchUntil, $excludeId)
            ->sortBy('start_datetime')
            ->values();

        for ($date = $firstDate->copy(); $date->lte($lastDate); $date->addDay()) {
            for ($minute = 8 * 60; $minute < 17 * 60; $minute++) {
                $candidate = $date->copy()->setTime(intdiv($minute, 60), $minute % 60);
                $candidateEnd = $candidate->copy()->addSeconds($duration);

                if (! self::isWithinOfficeHours($candidate, $candidateEnd)) {
                    continue;
                }

                $overlaps = $busy->contains(fn (EventRequest $event) =>
                    $event->start_datetime < $candidateEnd && $event->end_datetime > $candidate
                );

                if (! $overlaps) {
                    return [$candidate, $candidateEnd];
                }
            }
        }

        return null;
    }
}
