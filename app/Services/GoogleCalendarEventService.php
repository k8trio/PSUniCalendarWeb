<?php

namespace App\Services;

use App\Models\EventRequest;
use DomainException;
use Google\Client;
use Google\Exception as GoogleException;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class GoogleCalendarEventService
{
    public function isConfigured(): bool
    {
        return (bool) (config('services.google.service_account_json') && config('services.google.calendar_id'));
    }

    public function insert(EventRequest $event): string
    {
        $service = $this->service();
        $googleEvent = $service->events->insert(
            config('services.google.calendar_id'),
            new Event([
                'summary' => $event->title,
                'location' => $event->venue_name,
                'description' => $event->description,
                'start' => [
                    'dateTime' => date('c', strtotime($event->start_datetime)),
                    'timeZone' => 'Asia/Manila',
                ],
                'end' => [
                    'dateTime' => date('c', strtotime($event->end_datetime)),
                    'timeZone' => 'Asia/Manila',
                ],
            ])
        );

        return $googleEvent->getId();
    }

    public function delete(string $eventId): void
    {
        $this->service()->events->delete(config('services.google.calendar_id'), $eventId);
    }

    private function service(): Calendar
    {
        if (! $this->isConfigured()) {
            throw new DomainException('Google Calendar sync is not configured, so this decision cannot be undone or redone.');
        }

        try {
            $client = new Client();
            $client->setAuthConfig(config('services.google.service_account_json'));
            $client->addScope(Calendar::CALENDAR);

            return new Calendar($client);
        } catch (GoogleException|InvalidArgumentException $exception) {
            Log::error('Google Calendar client initialization failed for Planning Office decision history.', [
                'message' => $exception->getMessage(),
            ]);

            throw new DomainException('Google Calendar could not be reached. The decision was not changed; please try again.', previous: $exception);
        }
    }

    public function insertForHistory(EventRequest $event): string
    {
        try {
            return $this->insert($event);
        } catch (GoogleException|InvalidArgumentException $exception) {
            Log::error('Google Calendar insert failed while redoing a Planning Office approval.', [
                'event_request_id' => $event->id,
                'message' => $exception->getMessage(),
            ]);

            throw new DomainException('Google Calendar could not restore this approved event. The decision was not changed; please try again.', previous: $exception);
        }
    }

    public function deleteForHistory(string $eventId, int $requestId): void
    {
        try {
            $this->delete($eventId);
        } catch (GoogleException|InvalidArgumentException $exception) {
            Log::error('Google Calendar delete failed while undoing a Planning Office approval.', [
                'event_request_id' => $requestId,
                'google_event_id' => $eventId,
                'message' => $exception->getMessage(),
            ]);

            throw new DomainException('Google Calendar could not remove this approved event. The decision was not changed; please try again.', previous: $exception);
        }
    }
}
