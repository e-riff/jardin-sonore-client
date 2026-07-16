<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Mapper\SessionSummaryMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<SessionSummaryEntity>
 */
final class SessionSummaryDoctrineRepository extends ServiceEntityRepository implements SessionSummaryRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly SessionSummaryMapper $sessionSummaryMapper,
    ) {
        parent::__construct($managerRegistry, SessionSummaryEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?SessionSummary
    {
        $entity = $this->findOneBy(['uuid' => $uuid]);

        return $entity instanceof SessionSummaryEntity
            ? $this->sessionSummaryMapper->toDomain($entity)
            : null;
    }

    public function search(?string $query = null): array
    {
        $queryBuilder = $this->createQueryBuilder('summary')
            ->orderBy('summary.sessionDate', 'DESC')
            ->addOrderBy('summary.updatedAt', 'DESC')
            ->addOrderBy('summary.id', 'DESC');

        if (null !== $query && '' !== trim($query)) {
            $queryBuilder
                ->andWhere('LOWER(summary.title) LIKE LOWER(:query) OR LOWER(summary.organizationName) LIKE LOWER(:query)')
                ->setParameter('query', '%' . trim($query) . '%');
        }

        return array_map(
            fn ($entity): SessionSummary => $this->sessionSummaryMapper->toDomain($entity),
            $queryBuilder->getQuery()->getResult(),
        );
    }

    public function save(SessionSummary $sessionSummary): void
    {
        $entity = $this->findOneBy(['uuid' => $sessionSummary->getUuid()]);

        $this->getEntityManager()->persist($this->sessionSummaryMapper->toEntity(
            sessionSummary: $sessionSummary,
            sessionSummaryEntity: $entity instanceof SessionSummaryEntity ? $entity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
