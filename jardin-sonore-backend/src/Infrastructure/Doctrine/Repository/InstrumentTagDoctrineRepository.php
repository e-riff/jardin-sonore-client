<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\ContentCatalog\InstrumentTag;
use App\Domain\Repository\InstrumentTagRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\InstrumentTagEntity;
use App\Infrastructure\Doctrine\Mapper\InstrumentTagMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<InstrumentTagEntity>
 */
final class InstrumentTagDoctrineRepository extends ServiceEntityRepository implements InstrumentTagRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly InstrumentTagMapper $instrumentTagMapper,
    ) {
        parent::__construct($managerRegistry, InstrumentTagEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?InstrumentTag
    {
        $entity = $this->findOneBy(['uuid' => $uuid]);

        return $entity instanceof InstrumentTagEntity ? $this->instrumentTagMapper->toDomain($entity) : null;
    }

    public function findByLabel(string $label): ?InstrumentTag
    {
        $entity = $this->findOneBy(['label' => trim($label)]);

        return $entity instanceof InstrumentTagEntity ? $this->instrumentTagMapper->toDomain($entity) : null;
    }

    public function findByUuids(array $uuids): array
    {
        $normalizedUuids = array_values(array_unique(array_filter(
            array_map('trim', $uuids),
            static fn (string $uuid): bool => Uuid::isValid($uuid),
        )));

        if ([] === $normalizedUuids) {
            return [];
        }

        $entities = $this->findEntitiesByUuids($normalizedUuids);

        $byUuid = [];

        foreach ($entities as $entity) {
            $byUuid[$entity->getUuid()->toRfc4122()] = $this->instrumentTagMapper->toDomain($entity);
        }

        $tags = [];

        foreach ($normalizedUuids as $uuid) {
            if (isset($byUuid[$uuid])) {
                $tags[] = $byUuid[$uuid];
            }
        }

        return $tags;
    }

    /**
     * @param list<string> $uuids
     *
     * @return list<InstrumentTagEntity>
     */
    public function findEntitiesByUuids(array $uuids): array
    {
        if ([] === $uuids) {
            return [];
        }

        $rows = $this->getEntityManager()
            ->getConnection()
            ->createQueryBuilder()
            ->select('id')
            ->from('instrument_tag')
            ->where('LOWER(HEX(uuid)) IN (:uuids)')
            ->setParameter('uuids', array_map(
                static fn (string $uuid): string => strtolower(str_replace('-', '', $uuid)),
                $uuids,
            ), ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchFirstColumn();

        if ([] === $rows) {
            return [];
        }

        $entities = $this->createQueryBuilder('instrumentTag')
            ->andWhere('instrumentTag.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (mixed $id): int => (int) $id, $rows))
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $entities,
            static fn (mixed $entity): bool => $entity instanceof InstrumentTagEntity,
        ));
    }

    public function findAllOrderedByLabel(): array
    {
        $entities = $this->createQueryBuilder('instrumentTag')
            ->orderBy('instrumentTag.label', 'ASC')
            ->addOrderBy('instrumentTag.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn (InstrumentTagEntity $entity): InstrumentTag => $this->instrumentTagMapper->toDomain($entity),
            $entities,
        );
    }

    public function save(InstrumentTag $instrumentTag): void
    {
        $entity = $this->findOneBy(['uuid' => $instrumentTag->getUuid()]);

        $this->getEntityManager()->persist($this->instrumentTagMapper->toEntity(
            instrumentTag: $instrumentTag,
            instrumentTagEntity: $entity instanceof InstrumentTagEntity ? $entity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
