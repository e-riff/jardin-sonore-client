<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\InstrumentTagEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<InstrumentTagEntity>
 */
final class InstrumentTagEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, InstrumentTagEntity::class);
    }

    /**
     * @param list<string> $uuids
     *
     * @return list<InstrumentTagEntity>
     */
    public function findByUuids(array $uuids): array
    {
        if ([] === $uuids) {
            return [];
        }

        $entities = $this->createQueryBuilder('instrumentTag')
            ->andWhere('instrumentTag.uuid IN (:uuids)')
            ->setParameter('uuids', array_map(static fn (string $uuid): Uuid => Uuid::fromString($uuid), $uuids))
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $entities,
            static fn (mixed $entity): bool => $entity instanceof InstrumentTagEntity,
        ));
    }
}
