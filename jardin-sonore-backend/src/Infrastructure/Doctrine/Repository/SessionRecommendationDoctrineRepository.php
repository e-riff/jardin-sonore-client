<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Session\SessionRecommendation;
use App\Domain\Repository\SessionRecommendationRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\SessionRecommendationEntity;
use App\Infrastructure\Doctrine\Mapper\SessionRecommendationMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<SessionRecommendationEntity>
 */
final class SessionRecommendationDoctrineRepository extends ServiceEntityRepository implements SessionRecommendationRepositoryInterface
{
    public function __construct(ManagerRegistry $managerRegistry, private readonly SessionRecommendationMapper $sessionRecommendationMapper)
    {
        parent::__construct($managerRegistry, SessionRecommendationEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?SessionRecommendation
    {
        $entity = $this->findOneBy(['uuid' => $uuid]);

        return $entity instanceof SessionRecommendationEntity ? $this->sessionRecommendationMapper->toDomain($entity) : null;
    }

    public function search(?string $query = null, bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('recommendation')
            ->orderBy('recommendation.updatedAt', 'DESC')
            ->addOrderBy('recommendation.id', 'DESC');
        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('LOWER(recommendation.title) LIKE LOWER(:query)')->setParameter('query', '%' . trim($query) . '%');
        }
        if ($activeOnly) {
            $qb->andWhere('recommendation.active = :active')->setParameter('active', true);
        }

        return array_map(fn ($entity): SessionRecommendation => $this->sessionRecommendationMapper->toDomain($entity), $qb->getQuery()->getResult());
    }

    public function save(SessionRecommendation $sessionRecommendation): void
    {
        $entity = $this->findOneBy(['uuid' => $sessionRecommendation->getUuid()]);
        $this->getEntityManager()->persist($this->sessionRecommendationMapper->toEntity($sessionRecommendation, $entity instanceof SessionRecommendationEntity ? $entity : null));
        $this->getEntityManager()->flush();
    }
}
