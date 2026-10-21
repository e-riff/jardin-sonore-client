<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Commercial;

use App\Application\Mcp\ProjectContextReader;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialEventEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DoctrineProjectContextReader implements ProjectContextReader
{
    public function __construct(private ManagerRegistry $managerRegistry, private UrlGeneratorInterface $urlGenerator)
    {
    }

    /** @return array<string, mixed> */
    public function get(int $projectId): array
    {
        $projectEntity = $this->managerRegistry->getRepository(CommercialProjectEntity::class)->find($projectId);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw new RuntimeException('Project not found.');
        }

        $eventEntities = $this->managerRegistry->getRepository(CommercialEventEntity::class)
            ->createQueryBuilder('event')
            ->where('event.project = :project')
            ->andWhere('event.type IN (:eventTypes)')
            ->setParameter('project', $projectEntity)
            ->setParameter('eventTypes', ['note', 'action_completed', 'project_status_changed', 'project_confirmed', 'project_without_result', 'project_completed'])
            ->orderBy('event.occurredAt', 'DESC')
            ->addOrderBy('event.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()->getResult();
        $actionEntities = $this->managerRegistry->getRepository(CommercialActionEntity::class)
            ->createQueryBuilder('action')
            ->where('action.project = :project')
            ->andWhere('action.status = :status')
            ->setParameter('project', $projectEntity)
            ->setParameter('status', CommercialActionEntity::STATUS_OPEN)
            ->orderBy('action.dueOn', 'ASC')
            ->addOrderBy('action.id', 'ASC')
            ->setMaxResults(20)
            ->getQuery()->getResult();
        $appointmentEntities = $this->managerRegistry->getRepository(CommercialAppointmentEntity::class)
            ->createQueryBuilder('appointment')
            ->where('appointment.project = :project')
            ->setParameter('project', $projectEntity)
            ->orderBy('appointment.startsAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        $primaryContactEntity = $projectEntity->getPrimaryContact();

        return [
            'id' => $projectEntity->getId(),
            'title' => $projectEntity->getTitle(),
            'status' => $projectEntity->getStatus(),
            'organization' => $projectEntity->getOrganization()?->getName(),
            'primaryContact' => null === $primaryContactEntity ? null : trim($primaryContactEntity->getFirstName() . ' ' . $primaryContactEntity->getLastName()),
            'recentEvents' => array_map(static fn (CommercialEventEntity $eventEntity): array => [
                'id' => $eventEntity->getId(),
                'type' => $eventEntity->getType(),
                'occurredAt' => $eventEntity->getOccurredAt()->format(DATE_ATOM),
                'content' => null === $eventEntity->getContent() ? null : mb_substr($eventEntity->getContent(), 0, 2000),
            ], $eventEntities),
            'openActions' => array_map(static fn (CommercialActionEntity $actionEntity): array => [
                'id' => $actionEntity->getId(),
                'title' => $actionEntity->getTitle(),
                'dueOn' => $actionEntity->getDueOn()->format('Y-m-d'),
                'dueTime' => $actionEntity->getDueTime(),
                'revision' => hash('sha256', implode('|', [$actionEntity->getTitle(), $actionEntity->getDueOn()->format('Y-m-d'), $actionEntity->getDueTime() ?? '', $actionEntity->getStatus(), $actionEntity->getDetails() ?? ''])),
            ], $actionEntities),
            'appointments' => array_map(static fn (CommercialAppointmentEntity $appointmentEntity): array => [
                'id' => $appointmentEntity->getId(),
                'title' => $appointmentEntity->getTitle(),
                'startsAt' => $appointmentEntity->getStartsAt()->format(DATE_ATOM),
                'endsAt' => $appointmentEntity->getEndsAt()->format(DATE_ATOM),
                'location' => $appointmentEntity->getLocation(),
                'status' => $appointmentEntity->getStatus(),
                'summary' => null === $appointmentEntity->getSummary() ? null : mb_substr($appointmentEntity->getSummary(), 0, 2000),
                'revision' => $appointmentEntity->getRevision(),
                'syncWarning' => null !== $appointmentEntity->getSyncError(),
            ], $appointmentEntities),
            'url' => $this->urlGenerator->generate('commercial_project_show', ['id' => $projectId], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}
