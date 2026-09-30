<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Infrastructure\Doctrine\Entity\SessionOrganizationAvailabilityEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;

/** @extends ServiceEntityRepository<SessionOrganizationAvailabilityEntity> */
final class SessionOrganizationAvailabilityStore extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, SessionOrganizationAvailabilityEntity::class);
    }

    /**
     * Must run inside the session save transaction, after acquiring its write lock.
     *
     * @return list<int>
     */
    public function recordNewAvailability(SessionSummaryEntity $sessionSummaryEntity): array
    {
        if (!$sessionSummaryEntity->isPublished()) {
            return [];
        }
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        if (!$connection->isTransactionActive() || null === $sessionSummaryEntity->getId()) {
            throw new LogicException('Session availability requires a persisted session and an active save transaction.');
        }
        $queryBuilder = $connection->createQueryBuilder()
            ->select('organization_id')
            ->from('session_organization_availability')
            ->where('session_summary_id = :sessionId')
            ->setParameter('sessionId', $sessionSummaryEntity->getId());
        // A locking read sees availability recorded by a save that held the session lock before us.
        $knownOrganizationIds = array_map(intval(...), $connection->fetchFirstColumn($queryBuilder->getSQL() . ' FOR UPDATE', $queryBuilder->getParameters()));
        $newOrganizationIds = [];
        foreach ($sessionSummaryEntity->getOrganizations() as $organizationEntity) {
            $organizationId = $organizationEntity->getId();
            if (null === $organizationId) {
                throw new LogicException('Session availability requires persisted organizations.');
            }
            if (in_array($organizationId, $knownOrganizationIds, true)) {
                continue;
            }
            $entityManager->persist(new SessionOrganizationAvailabilityEntity($sessionSummaryEntity, $organizationEntity));
            $newOrganizationIds[] = $organizationId;
        }

        return $newOrganizationIds;
    }
}
