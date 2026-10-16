<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Commercial;

use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\GroupCalendarEvents;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GroupCalendarEventsTest extends TestCase
{
    public function testSameTitleAndMovedRecurrenceAreSuggestedTogether(): void
    {
        $events = [
            $this->event('series-1_20261119', 'IPE - Jardin', '2026-11-03', 'series-1', '2026-11-19'),
            $this->event('single-1', 'Autre séance', '2026-11-04'),
            $this->event('series-1_20261217', 'IPE - Jardin déplacé', '2026-12-01', 'series-1', '2026-12-17'),
            $this->event('single-2', ' IPE - Jardin ', '2026-12-08'),
        ];

        $groups = (new GroupCalendarEvents())->group($events);

        self::assertCount(2, $groups);
        self::assertSame(['series-1_20261119', 'series-1_20261217', 'single-2'], array_map(static fn (CalendarEventOccurrence $event): string => $event->id, $groups[0]));
        self::assertSame(['single-1'], array_map(static fn (CalendarEventOccurrence $event): string => $event->id, $groups[1]));
    }

    public function testGroupsKeepEventsSeparateForExplicitSelection(): void
    {
        $events = [
            $this->event('first', 'Même titre', '2026-11-03'),
            $this->event('second', 'Même titre', '2026-11-10'),
        ];

        $groups = (new GroupCalendarEvents())->group($events);

        self::assertCount(1, $groups);
        self::assertCount(2, $groups[0]);
        self::assertNotSame($groups[0][0]->id, $groups[0][1]->id);
    }

    private function event(string $id, string $title, string $date, ?string $seriesId = null, ?string $originalDate = null): CalendarEventOccurrence
    {
        return new CalendarEventOccurrence(
            $id,
            $title,
            new DateTimeImmutable("{$date} 09:00:00 Europe/Paris"),
            new DateTimeImmutable("{$date} 11:00:00 Europe/Paris"),
            null,
            false,
            $seriesId,
            null === $originalDate ? null : new DateTimeImmutable("{$originalDate} 09:00:00 Europe/Paris"),
        );
    }
}
