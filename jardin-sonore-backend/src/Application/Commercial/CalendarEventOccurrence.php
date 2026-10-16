<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use DateTimeImmutable;

final readonly class CalendarEventOccurrence
{
    public function __construct(
        public string $id,
        public string $title,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public ?string $location,
        public bool $allDay,
        public ?string $recurringEventId = null,
        public ?DateTimeImmutable $originalStartsAt = null,
        public string $etag = '',
        public string $timeZone = 'Europe/Paris',
        public string $googleStatus = 'confirmed',
    ) {
    }
}
