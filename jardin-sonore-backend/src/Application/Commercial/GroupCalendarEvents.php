<?php

declare(strict_types=1);

namespace App\Application\Commercial;

final readonly class GroupCalendarEvents
{
    /**
     * @param list<CalendarEventOccurrence> $events
     *
     * @return list<list<CalendarEventOccurrence>>
     */
    public function group(array $events): array
    {
        usort($events, static fn (CalendarEventOccurrence $first, CalendarEventOccurrence $second): int => $first->startsAt <=> $second->startsAt ?: strcmp($first->id, $second->id));

        $groups = [];
        foreach ($events as $event) {
            $matchingKeys = [];
            foreach ($groups as $key => $group) {
                foreach ($group as $groupEvent) {
                    if ($this->matches($event, $groupEvent)) {
                        $matchingKeys[] = $key;
                        break;
                    }
                }
            }

            if ([] === $matchingKeys) {
                $groups[] = [$event];
                continue;
            }

            $firstKey = array_shift($matchingKeys);
            $groups[$firstKey][] = $event;
            foreach ($matchingKeys as $key) {
                array_push($groups[$firstKey], ...$groups[$key]);
                unset($groups[$key]);
            }
        }

        $groups = array_values($groups);
        foreach ($groups as &$group) {
            usort($group, static fn (CalendarEventOccurrence $first, CalendarEventOccurrence $second): int => $first->startsAt <=> $second->startsAt ?: strcmp($first->id, $second->id));
        }
        unset($group);
        usort($groups, static fn (array $first, array $second): int => $first[0]->startsAt <=> $second[0]->startsAt);

        return $groups;
    }

    private function matches(CalendarEventOccurrence $first, CalendarEventOccurrence $second): bool
    {
        if (null !== $first->recurringEventId && $first->recurringEventId === $second->recurringEventId) {
            return true;
        }

        $firstTitle = mb_strtolower(trim($first->title));

        return '' !== $firstTitle && $firstTitle === mb_strtolower(trim($second->title));
    }
}
