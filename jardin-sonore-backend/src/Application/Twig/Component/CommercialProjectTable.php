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

    /** @return array<string, list<CommercialProjectRow>> */
    public function getSections(): array
    {
        $localDate = $this->clock->now()->setTimezone(new DateTimeZone('Europe/Paris'))->setTime(0, 0);
        $sections = ['priority' => [], 'upcoming' => [], 'without_task' => [], 'closed' => []];
        $query = mb_strtolower(trim($this->query));
        foreach ($this->queue->forDate($localDate, 'active' !== $this->status) as $row) {
            if (!$this->matchesStatus($row) || ('' !== $query && !str_contains(mb_strtolower("{$row->title} {$row->organizationLabel} {$row->nextActionTitle}"), $query))) {
                continue;
            }
            $sections[$row->bucket][] = $row;
        }
        foreach ($sections as &$rows) {
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

        return ($left->nextDueOn?->format('Y-m-d') ?? '') <=> ($right->nextDueOn?->format('Y-m-d') ?? '') ?: $left->id <=> $right->id;
    }
}
