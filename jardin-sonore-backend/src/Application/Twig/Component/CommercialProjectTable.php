<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Application\Commercial\CommercialProjectQueue;
use App\Application\Commercial\CommercialProjectRow;
use DateTimeZone;
use Symfony\Component\Clock\ClockInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'CommercialProjectTable', template: 'components/CommercialProjectTable.html.twig', method: 'get')]
final class CommercialProjectTable
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    #[LiveProp(writable: true, url: true)]
    public string $status = 'active';

    #[LiveProp(writable: true, url: true)]
    public string $sort = 'urgency';

    public function __construct(private readonly CommercialProjectQueue $queue, private readonly ClockInterface $clock)
    {
    }

    /** @return array{priority: list<CommercialProjectRow>, upcoming: list<CommercialProjectRow>, upcoming_periods: array{this_week: list<CommercialProjectRow>, next_week: list<CommercialProjectRow>, later: list<CommercialProjectRow>}, without_task: list<CommercialProjectRow>, confirmed: list<CommercialProjectRow>, closed: list<CommercialProjectRow>} */
    public function getSections(): array
    {
        $localDate = $this->clock->now()->setTimezone(new DateTimeZone('Europe/Paris'))->setTime(0, 0);
        $sections = [
            'priority' => [],
            'upcoming' => [],
            'upcoming_periods' => ['this_week' => [], 'next_week' => [], 'later' => []],
            'without_task' => [],
            'confirmed' => [],
            'closed' => [],
        ];
        $query = mb_strtolower(trim($this->query));
        foreach ($this->queue->forDate($localDate, 'active' !== $this->status) as $row) {
            if (!$this->matchesStatus($row) || ('' !== $query && !str_contains(mb_strtolower("{$row->title} {$row->organizationLabel} {$row->organizationLocation} {$row->nextActionTitle}"), $query))) {
                continue;
            }
            $sections[$row->bucket][] = $row;
        }
        foreach (['priority', 'upcoming', 'without_task', 'confirmed', 'closed'] as $bucket) {
            $rows = $sections[$bucket];
            usort($rows, fn (CommercialProjectRow $left, CommercialProjectRow $right): int => $this->compare($left, $right));
            $sections[$bucket] = $rows;
        }

        $nextWeekStart = $localDate->modify('monday next week');
        $nextWeekEnd = $nextWeekStart->modify('sunday this week');
        $nextWeekStartDate = $nextWeekStart->format('Y-m-d');
        $nextWeekEndDate = $nextWeekEnd->format('Y-m-d');
        foreach ($sections['upcoming'] as $row) {
            $dueDate = $row->nextDueOn?->format('Y-m-d');
            $period = match (true) {
                $dueDate < $nextWeekStartDate => 'this_week',
                $dueDate <= $nextWeekEndDate => 'next_week',
                default => 'later',
            };
            $sections['upcoming_periods'][$period][] = $row;
        }
        foreach ($sections['upcoming_periods'] as &$rows) {
            usort($rows, fn (CommercialProjectRow $left, CommercialProjectRow $right): int => $this->compare($left, $right));
        }

        return $sections;
    }

    private function matchesStatus(CommercialProjectRow $row): bool
    {
        return match ($this->status) {
            'active' => 'closed' !== $row->bucket,
            'closed' => 'closed' === $row->bucket,
            'all' => true,
            'discussion', 'confirmed', 'completed', 'without_result' => $row->status === $this->status,
            default => false,
        };
    }

    private function compare(CommercialProjectRow $left, CommercialProjectRow $right): int
    {
        if ('name' === $this->sort) {
            return strnatcasecmp($left->organizationLabel, $right->organizationLabel) ?: $left->id <=> $right->id;
        }
        if ('recent' === $this->sort || 'without_task' === $left->bucket || 'closed' === $left->bucket) {
            return $right->createdAt <=> $left->createdAt ?: $right->id <=> $left->id;
        }
        if ('confirmed' === $left->bucket) {
            $leftActivity = $left->lastEventAt ?? $left->createdAt;
            $rightActivity = $right->lastEventAt ?? $right->createdAt;

            return $leftActivity <=> $rightActivity ?: $left->id <=> $right->id;
        }

        return ($left->nextDueOn?->format('Y-m-d') ?? '') <=> ($right->nextDueOn?->format('Y-m-d') ?? '') ?: $left->id <=> $right->id;
    }
}
