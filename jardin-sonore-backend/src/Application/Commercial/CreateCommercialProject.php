<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectPersonEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Symfony\Component\Clock\ClockInterface;

final readonly class CreateCommercialProject
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function manual(
        OrganizationEntity $organizationEntity,
        ?PersonEntity $personEntity,
        string $firstMessage,
        ?string $title,
        string $initialStatus,
        ?string $taskTitle,
        ?DateTimeImmutable $taskDueOn,
        ?string $taskDueTime = null,
    ): CommercialProjectEntity {
        if (!$organizationEntity->isActive()) {
            throw new DomainException('A manual dossier needs an active organization.');
        }
        if ($personEntity instanceof PersonEntity && (!$personEntity->isActive() || $personEntity->getOrganization() !== $organizationEntity)) {
            throw new DomainException('The person must belong to the selected organization.');
        }
        $firstMessage = trim($firstMessage);
        if ('' === $firstMessage) {
            throw new DomainException('The first message cannot be empty.');
        }
        if (!in_array($initialStatus, [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED], true)) {
            throw new DomainException('The initial dossier status must be open or confirmed.');
        }
        $taskTitle = null === $taskTitle ? null : trim($taskTitle);
        if (('' !== $taskTitle && null !== $taskTitle) !== ($taskDueOn instanceof DateTimeImmutable)) {
            throw new DomainException('A task needs both a title and a due date.');
        }

        $now = $this->clock->now();
        $title = trim($title ?? '');
        if ('' === $title) {
            $title = $organizationEntity->getName() . ' · ' . $now->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y');
        }
        $projectEntity = new CommercialProjectEntity($title, $organizationEntity, null, $now);
        $projectEntity->setStatus($initialStatus);
        if ($personEntity instanceof PersonEntity) {
            $projectEntity->setPrimaryContact($personEntity);
            $projectEntity->addPerson(new CommercialProjectPersonEntity($projectEntity, $personEntity, $now));
        }
        $projectEntity->recordEvent('initial_message', $now, $firstMessage, [], $personEntity);
        if (null !== $taskTitle && '' !== $taskTitle && $taskDueOn instanceof DateTimeImmutable) {
            $actionEntity = new CommercialActionEntity($projectEntity, $taskTitle, $taskDueOn, null, $personEntity);
            $actionEntity->setDueTime($taskDueTime);
            $projectEntity->addAction($actionEntity);
        }

        return $projectEntity;
    }
}
