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

    public function complete(CommercialActionEntity $actionEntity, ?string $note, ?string $correctedTitle = null): void
    {
        $this->assertOpen($actionEntity);

        if (null !== $correctedTitle) {
            $correctedTitle = trim($correctedTitle);
            if ('' === $correctedTitle || 255 < mb_strlen($correctedTitle)) {
                throw new DomainException('A completed action needs a valid title.');
            }
            $actionEntity->revise($correctedTitle, $actionEntity->getDueOn(), $actionEntity->getDetails(), $actionEntity->getPerson());
        }

        $now = $this->clock->now();
        $actionEntity->resolve(CommercialActionEntity::STATUS_DONE, $now);
        $actionEntity->getProject()->recordEvent('action_completed', $now, $note, ['action_title' => $actionEntity->getTitle()], $actionEntity->getPerson())->linkAction($actionEntity);
    }

    public function reviseCompleted(CommercialActionEntity $actionEntity, string $title, ?string $note): void
    {
        if (CommercialActionEntity::STATUS_DONE !== $actionEntity->getStatus()) {
            throw new DomainException('Only a completed action can be corrected.');
        }
        $title = trim($title);
        if ('' === $title || 255 < mb_strlen($title)) {
            throw new DomainException('A completed action needs a valid title.');
        }
        foreach ($actionEntity->getProject()->getEvents() as $eventEntity) {
            if ('action_completed' === $eventEntity->getType() && $eventEntity->getAction() === $actionEntity) {
                $actionEntity->revise($title, $actionEntity->getDueOn(), $actionEntity->getDetails(), $actionEntity->getPerson());
                $eventEntity->revise($note, [...$eventEntity->getMetadata(), 'action_title' => $title]);

                return;
            }
        }

        throw new DomainException('The completed action has no linked timeline entry.');
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
        ])->linkAction($actionEntity);
    }

    public function cancel(CommercialActionEntity $actionEntity): void
    {
        $this->assertOpen($actionEntity);

        $now = $this->clock->now();
        $actionEntity->resolve(CommercialActionEntity::STATUS_CANCELED, $now);
        $actionEntity->getProject()->recordEvent('action_canceled', $now, $actionEntity->getTitle())->linkAction($actionEntity);
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
        ], $personEntity)->linkAction($actionEntity);
    }

    private function assertOpen(CommercialActionEntity $actionEntity): void
    {
        if (CommercialActionEntity::STATUS_OPEN !== $actionEntity->getStatus()) {
            throw new DomainException('Only an open action can be changed.');
        }
    }
}
