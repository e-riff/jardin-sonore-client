<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use DateTimeImmutable;
use DomainException;
use Symfony\Component\Clock\ClockInterface;

final readonly class CommercialActionWorkflow
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function complete(CommercialActionEntity $actionEntity, ?string $note): void
    {
        $this->assertOpen($actionEntity);

        $now = $this->clock->now();
        $actionEntity->resolve(CommercialActionEntity::STATUS_DONE, $now);
        $actionEntity->getProject()->recordEvent('action_completed', $now, $note, ['action_title' => $actionEntity->getTitle()], $actionEntity->getPerson());
    }

    public function postpone(CommercialActionEntity $actionEntity, DateTimeImmutable $dueOn): void
    {
        $this->assertOpen($actionEntity);

        $oldDueOn = $actionEntity->getDueOn();
        if ($oldDueOn->format('Y-m-d') === $dueOn->format('Y-m-d')) {
            return;
        }

        $actionEntity->setDueOn($dueOn);
        $actionEntity->getProject()->recordEvent('action_postponed', $this->clock->now(), null, [
            'old_due_on' => $oldDueOn->format('Y-m-d'),
            'new_due_on' => $dueOn->format('Y-m-d'),
        ]);
    }

    public function cancel(CommercialActionEntity $actionEntity): void
    {
        $this->assertOpen($actionEntity);

        $now = $this->clock->now();
        $actionEntity->resolve(CommercialActionEntity::STATUS_CANCELED, $now);
        $actionEntity->getProject()->recordEvent('action_canceled', $now, $actionEntity->getTitle());
    }

    public function revise(CommercialActionEntity $actionEntity, string $title, DateTimeImmutable $dueOn, ?string $details, ?PersonEntity $personEntity): void
    {
        $this->assertOpen($actionEntity);
        $title = trim($title);
        if ('' === $title) {
            throw new DomainException('An action needs a title.');
        }
        $oldTitle = $actionEntity->getTitle();
        $oldDueOn = $actionEntity->getDueOn();
        $actionEntity->revise($title, $dueOn, $details, $personEntity);
        $actionEntity->getProject()->recordEvent('action_edited', $this->clock->now(), $title, [
            'old_title' => $oldTitle,
            'old_due_on' => $oldDueOn->format('Y-m-d'),
            'new_due_on' => $dueOn->format('Y-m-d'),
        ], $personEntity);
    }

    private function assertOpen(CommercialActionEntity $actionEntity): void
    {
        if (CommercialActionEntity::STATUS_OPEN !== $actionEntity->getStatus()) {
            throw new DomainException('Only an open action can be changed.');
        }
    }
}
