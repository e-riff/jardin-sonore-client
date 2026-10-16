<?php

declare(strict_types=1);

namespace App\Infrastructure\Google;

use App\Application\Commercial\CalendarEventDraft;
use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialCalendarGateway;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class GoogleCalendarGateway implements CommercialCalendarGateway
{
    private const string API_BASE_URL = 'https://www.googleapis.com/calendar/v3/calendars/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private GoogleCalendarOAuthClient $oauthClient,
        #[Autowire('%app.commercial.calendar_id%')] private string $calendarId,
    ) {
    }

    public function eventsBetween(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array
    {
        if ($endsAt <= $startsAt) {
            throw new GoogleCalendarApiException('The Calendar search end must follow its start.');
        }
        $this->assertCalendarConfigured();
        $events = [];
        $pageToken = null;
        do {
            $query = [
                'timeMin' => $startsAt->format(DATE_RFC3339),
                'timeMax' => $endsAt->format(DATE_RFC3339),
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'maxResults' => 2500,
            ];
            if (null !== $pageToken) {
                $query['pageToken'] = $pageToken;
            }
            $data = $this->request('GET', $this->eventsUrl(), ['query' => $query]);
            foreach ($data['items'] ?? [] as $eventData) {
                if (is_array($eventData) && 'cancelled' !== ($eventData['status'] ?? null)) {
                    $events[] = $this->mapEvent($eventData);
                }
            }
            $pageToken = $data['nextPageToken'] ?? null;
        } while (is_string($pageToken) && '' !== $pageToken);

        return $events;
    }

    public function event(string $eventId): ?CalendarEventOccurrence
    {
        $this->assertCalendarConfigured();
        $data = $this->request('GET', $this->eventsUrl() . '/' . rawurlencode($eventId), allowMissing: true);
        if ([] === $data || 'cancelled' === ($data['status'] ?? null)) {
            return null;
        }

        return $this->mapEvent($data);
    }

    public function createEvent(CalendarEventDraft $draft, string $requestId): CalendarEventOccurrence
    {
        $this->assertCalendarConfigured();
        if (1 !== preg_match('/^[0-9a-f]{32}$/', $requestId)) {
            throw new GoogleCalendarApiException('The Calendar request ID is invalid.');
        }
        try {
            $data = $this->request('POST', $this->eventsUrl(), [
                'json' => ['id' => $requestId] + $this->eventPayload($draft),
            ]);
        } catch (GoogleCalendarConflictException) {
            $existingEvent = $this->event($requestId);
            if (null === $existingEvent) {
                throw new GoogleCalendarApiException('The idempotent Calendar event could not be recovered.');
            }

            return $existingEvent;
        }

        return $this->mapEvent($data);
    }

    public function updateEvent(CalendarEventOccurrence $currentEvent, CalendarEventDraft $draft): CalendarEventOccurrence
    {
        $this->assertCalendarConfigured();
        $data = $this->request('PATCH', $this->eventsUrl() . '/' . rawurlencode($currentEvent->id), [
            'headers' => ['If-Match' => $currentEvent->etag],
            'json' => $this->eventPayload($draft),
        ]);

        return $this->mapEvent($data);
    }

    public function deleteEvent(CalendarEventOccurrence $currentEvent): void
    {
        $this->assertCalendarConfigured();
        $this->request('DELETE', $this->eventsUrl() . '/' . rawurlencode($currentEvent->id), [
            'headers' => ['If-Match' => $currentEvent->etag],
        ], true);
    }

    /** @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $options = [], bool $allowMissing = false): array
    {
        $options['auth_bearer'] = $this->oauthClient->getAccessToken();
        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            throw new GoogleCalendarApiException('Google Calendar could not be reached.', previous: $exception);
        }

        if (in_array($statusCode, [409, 412], true)) {
            throw new GoogleCalendarConflictException('The Google Calendar event changed after it was read.');
        }
        if ($allowMissing && in_array($statusCode, [404, 410], true)) {
            $this->oauthClient->markAccessSucceeded();

            return [];
        }
        if (200 > $statusCode || 300 <= $statusCode) {
            throw new GoogleCalendarApiException('Google Calendar rejected the request.');
        }
        try {
            $data = '' === $content ? [] : json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GoogleCalendarApiException('Google Calendar returned an invalid response.', previous: $exception);
        }
        if (!is_array($data)) {
            throw new GoogleCalendarApiException('Google Calendar returned an invalid response.');
        }
        $this->oauthClient->markAccessSucceeded();

        return $data;
    }

    /** @param array<string, mixed> $eventData */
    private function mapEvent(array $eventData): CalendarEventOccurrence
    {
        $startData = $eventData['start'] ?? null;
        $endData = $eventData['end'] ?? null;
        if (!is_array($startData) || !is_array($endData)) {
            throw new GoogleCalendarApiException('Google returned an event without a start or end.');
        }
        $timeZone = $startData['timeZone'] ?? $endData['timeZone'] ?? 'Europe/Paris';
        if (!is_string($timeZone)) {
            $timeZone = 'Europe/Paris';
        }
        try {
            $dateTimeZone = new DateTimeZone($timeZone);
        } catch (Throwable $exception) {
            throw new GoogleCalendarApiException('Google returned an invalid time zone.', previous: $exception);
        }
        $allDay = isset($startData['date']);
        $startsAt = $this->eventDate($startData, $dateTimeZone);
        $endsAt = $this->eventDate($endData, $dateTimeZone);
        if ($endsAt <= $startsAt) {
            throw new GoogleCalendarApiException('Google returned an event with an invalid time range.');
        }
        $originalStartData = $eventData['originalStartTime'] ?? null;
        $originalStartsAt = is_array($originalStartData) ? $this->eventDate($originalStartData, $dateTimeZone) : null;

        return new CalendarEventOccurrence(
            is_string($eventData['id'] ?? null) ? $eventData['id'] : throw new GoogleCalendarApiException('Google returned an event without an ID.'),
            is_string($eventData['summary'] ?? null) ? $eventData['summary'] : '(Sans titre)',
            $startsAt,
            $endsAt,
            is_string($eventData['location'] ?? null) ? $eventData['location'] : null,
            $allDay,
            is_string($eventData['recurringEventId'] ?? null) ? $eventData['recurringEventId'] : null,
            $originalStartsAt,
            is_string($eventData['etag'] ?? null) ? $eventData['etag'] : '',
            $timeZone,
            is_string($eventData['status'] ?? null) ? $eventData['status'] : 'confirmed',
        );
    }

    /** @param array<string, mixed> $dateData */
    private function eventDate(array $dateData, DateTimeZone $fallbackTimeZone): DateTimeImmutable
    {
        $date = $dateData['dateTime'] ?? $dateData['date'] ?? null;
        if (!is_string($date)) {
            throw new GoogleCalendarApiException('Google returned an event without a valid date.');
        }

        return new DateTimeImmutable($date, $fallbackTimeZone);
    }

    private function eventsUrl(): string
    {
        return self::API_BASE_URL . rawurlencode($this->calendarId) . '/events';
    }

    /** @return array<string, mixed> */
    private function eventPayload(CalendarEventDraft $draft): array
    {
        return [
            'summary' => $draft->title,
            'start' => [
                'dateTime' => $draft->startsAt->format(DATE_RFC3339),
                'timeZone' => $draft->timeZone,
            ],
            'end' => [
                'dateTime' => $draft->endsAt->format(DATE_RFC3339),
                'timeZone' => $draft->timeZone,
            ],
            'location' => $draft->location,
        ];
    }

    private function assertCalendarConfigured(): void
    {
        if ('' === $this->calendarId) {
            throw new GoogleCalendarApiException('Google Calendar ID is not configured.');
        }
    }
}
