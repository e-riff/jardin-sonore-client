<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Commercial;

use App\Application\Mcp\MailSource;
use App\Application\Mcp\ProjectFollowupWriter;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialEventEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DoctrineProjectFollowupWriter implements ProjectFollowupWriter
{
    public function __construct(private EntityManagerInterface $entityManager, private UrlGeneratorInterface $urlGenerator)
    {
    }

    /** @return array<string, mixed> */
    public function recordSummary(int $projectId, MailSource $source, string $summary): array
    {
        return $this->entityManager->wrapInTransaction(function () use ($projectId, $source, $summary): array {
            $projectEntity = $this->lockedProject($projectId);
            $eventEntity = $this->entityManager->getRepository(CommercialEventEntity::class)->findOneBy([
                'project' => $projectEntity,
                'mailSourceKey' => $source->sourceKey,
            ]);
            if ($eventEntity instanceof CommercialEventEntity) {
                return $this->result($projectId, 'eventId', $eventEntity->getId(), true);
            }

            $eventEntity = $projectEntity->recordEvent('note', new DateTimeImmutable(), $summary, [
                'source' => 'mail',
                'mailReference' => $source->reference,
                'mailDate' => $source->date,
                'mailFrom' => $source->from,
                'mailSubject' => $source->subject ?? '',
                'messageId' => $source->messageId ?? '',
            ]);
            $eventEntity->setMailSourceKey($source->sourceKey);
            $this->entityManager->persist($eventEntity);
            $this->entityManager->flush();

            return $this->result($projectId, 'eventId', $eventEntity->getId(), false);
        });
    }

    /** @return array<string, mixed> */
    public function createReminder(int $projectId, MailSource $source, string $title, DateTimeImmutable $dueOn): array
    {
        return $this->entityManager->wrapInTransaction(function () use ($projectId, $source, $title, $dueOn): array {
            $projectEntity = $this->lockedProject($projectId);
            $actionEntity = $this->entityManager->getRepository(CommercialActionEntity::class)->findOneBy([
                'project' => $projectEntity,
                'mailSourceKey' => $source->sourceKey,
            ]);
            if ($actionEntity instanceof CommercialActionEntity) {
                return $this->result($projectId, 'actionId', $actionEntity->getId(), true);
            }
            if (in_array($projectEntity->getStatus(), [CommercialProjectEntity::STATUS_COMPLETED, CommercialProjectEntity::STATUS_WITHOUT_RESULT], true)) {
                throw new RuntimeException('Closed projects cannot receive reminders.');
            }

            $actionEntity = new CommercialActionEntity($projectEntity, $title, $dueOn);
            $actionEntity->setMailSourceKey($source->sourceKey);
            $projectEntity->addAction($actionEntity);
            $this->entityManager->persist($actionEntity);
            $eventEntity = $projectEntity->recordEvent('action_created', new DateTimeImmutable(), $title);
            $eventEntity->linkAction($actionEntity);
            $this->entityManager->persist($eventEntity);
            $this->entityManager->flush();

            return $this->result($projectId, 'actionId', $actionEntity->getId(), false);
        });
    }

    private function lockedProject(int $projectId): CommercialProjectEntity
    {
        $projectEntity = $this->entityManager->find(CommercialProjectEntity::class, $projectId, LockMode::PESSIMISTIC_WRITE);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw new RuntimeException('Project not found.');
        }

        return $projectEntity;
    }

    /** @return array<string, mixed> */
    private function result(int $projectId, string $idName, int $recordId, bool $alreadyExists): array
    {
        return [
            'projectId' => $projectId,
            $idName => $recordId,
            'alreadyExists' => $alreadyExists,
            'url' => $this->urlGenerator->generate('commercial_project_show', ['id' => $projectId], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}
