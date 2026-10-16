<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;

final readonly class CalendarEventDraft
{
    public function __construct(
        public string $title,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $timeZone,
        public ?string $location,
    ) {
        if ('' === trim($title) || $endsAt <= $startsAt) {
            throw new DomainException('A calendar event requires a title and a valid time range.');
        }

        new DateTimeZone($timeZone);
    }
}
