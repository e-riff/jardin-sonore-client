<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use DateTimeImmutable;

interface CommercialCalendarGateway
{
    /** @return list<CalendarEventOccurrence> */
    public function eventsBetween(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array;

    public function event(string $eventId): ?CalendarEventOccurrence;

    public function createEvent(CalendarEventDraft $draft, string $requestId): CalendarEventOccurrence;

    public function updateEvent(CalendarEventOccurrence $currentEvent, CalendarEventDraft $draft): CalendarEventOccurrence;

    public function deleteEvent(CalendarEventOccurrence $currentEvent): void;
}
