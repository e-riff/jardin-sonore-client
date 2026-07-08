<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\ContentCatalog\Instrument;
use App\Domain\Model\ContentCatalog\InstrumentTag;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Application\ContentCatalog\InstrumentCatalogCriteria;
use App\Application\ContentCatalog\InstrumentCatalogItem;
use App\Application\ContentCatalog\InstrumentCatalogQueryInterface;
use App\Application\ContentCatalog\InstrumentCatalogResult;
use App\Infrastructure\Doctrine\Entity\InstrumentEntity;
use App\Infrastructure\Doctrine\Entity\InstrumentTagEntity;
use App\Infrastructure\Doctrine\Mapper\InstrumentMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<InstrumentEntity>
 */
final class InstrumentDoctrineRepository extends ServiceEntityRepository implements InstrumentRepositoryInterface, InstrumentCatalogQueryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly InstrumentMapper $instrumentMapper,
    ) {
        parent::__construct($managerRegistry, InstrumentEntity::class);
    }

    public function findEntityByUuid(Uuid $uuid): ?InstrumentEntity
    {
        $entity = $this->findOneBy(['uuid' => $uuid]);

        return $entity instanceof InstrumentEntity ? $entity : null;
    }

    public function findByUuid(Uuid $uuid): ?Instrument
    {
        $entity = $this->findOneBy(['uuid' => $uuid]);

        return $entity instanceof InstrumentEntity ? $this->instrumentMapper->toDomain($entity) : null;
    }

    public function save(Instrument $instrument): void
    {
        $entity = $this->findOneBy(['uuid' => $instrument->getUuid()]);

        $instrumentEntity = $this->instrumentMapper->toEntity(
            instrument: $instrument,
            instrumentEntity: $entity instanceof InstrumentEntity ? $entity : null,
        );

        $this->synchronizeTags($instrumentEntity, $instrument->getTags());

        $this->getEntityManager()->persist($instrumentEntity);
        $this->getEntityManager()->flush();
    }

    public function findCatalogItems(InstrumentCatalogCriteria $criteria): InstrumentCatalogResult
    {
        $idsQueryBuilder = $this->createQueryBuilder('instrument')
            ->select('instrument.id AS id')
            ->addSelect('MIN(instrument.name) AS sort_name')
            ->addSelect('MIN(COALESCE(instrument.tuning, \'\')) AS sort_tuning')
            ->addSelect('MIN(COALESCE(instrument.quantity, -1)) AS sort_quantity')
            ->addSelect('MIN(CASE WHEN instrument.active = true THEN 1 ELSE 0 END) AS sort_active')
            ->addSelect('MIN(instrument.updatedAt) AS sort_updated_at')
            ->leftJoin('instrument.tags', 'tag');

        $this->applyCatalogCriteria($idsQueryBuilder, $criteria);
        $idsQueryBuilder->groupBy('instrument.id');
        $this->applyCatalogSorting($idsQueryBuilder, $criteria);

        $matchingIds = array_values(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $idsQueryBuilder->getQuery()->getScalarResult(),
        ));

        $total = count($matchingIds);
        $offset = max(0, ($criteria->page - 1) * $criteria->perPage);
        $pagedIds = array_slice($matchingIds, $offset, $criteria->perPage);

        if ([] === $pagedIds) {
            return new InstrumentCatalogResult([], $total);
        }

        $entities = $this->createQueryBuilder('instrument')
            ->addSelect('tag')
            ->leftJoin('instrument.tags', 'tag')
            ->andWhere('instrument.id IN (:ids)')
            ->setParameter('ids', $pagedIds)
            ->getQuery()
            ->getResult();

        $orderById = array_flip($pagedIds);

        usort($entities, static function (InstrumentEntity $left, InstrumentEntity $right) use ($orderById): int {
            return ($orderById[$left->getId()] ?? 0) <=> ($orderById[$right->getId()] ?? 0);
        });

        $items = [];

        foreach ($entities as $entity) {
            if (!$entity instanceof InstrumentEntity) {
                continue;
            }

            $items[] = new InstrumentCatalogItem(
                uuid: $entity->getUuid(),
                name: $entity->getName(),
                tuning: $entity->getTuning(),
                quantity: $entity->getQuantity(),
                notes: $entity->getNotes(),
                active: $entity->isActive(),
                updatedAt: $entity->getUpdatedAt(),
                tags: array_values(array_map(
                    static fn (InstrumentTagEntity $instrumentTagEntity): string => $instrumentTagEntity->getLabel(),
                    $entity->getTags()->toArray(),
                )),
            );
        }

        return new InstrumentCatalogResult($items, $total);
    }

    /**
     * @param list<InstrumentTag> $instrumentTags
     */
    private function synchronizeTags(InstrumentEntity $instrumentEntity, array $instrumentTags): void
    {
        $targetTagUuids = array_values(array_unique(array_map(
            static fn (InstrumentTag $instrumentTag): string => $instrumentTag->getUuid()->toRfc4122(),
            $instrumentTags,
        )));

        $currentTagEntitiesByUuid = [];

        foreach ($instrumentEntity->getTags() as $instrumentTagEntity) {
            $currentTagEntitiesByUuid[$instrumentTagEntity->getUuid()->toRfc4122()] = $instrumentTagEntity;
        }

        foreach ($currentTagEntitiesByUuid as $uuid => $instrumentTagEntity) {
            if (!in_array($uuid, $targetTagUuids, true)) {
                $instrumentEntity->removeTag($instrumentTagEntity);
            }
        }

        if ([] === $targetTagUuids) {
            return;
        }

        $targetTagEntities = $this->getEntityManager()
            ->getRepository(InstrumentTagEntity::class)
            ->createQueryBuilder('instrumentTag')
            ->andWhere('instrumentTag.uuid IN (:uuids)')
            ->setParameter('uuids', array_map(static fn (string $uuid): Uuid => Uuid::fromString($uuid), $targetTagUuids))
            ->getQuery()
            ->getResult();

        $targetTagEntitiesByUuid = [];

        foreach ($targetTagEntities as $instrumentTagEntity) {
            if ($instrumentTagEntity instanceof InstrumentTagEntity) {
                $targetTagEntitiesByUuid[$instrumentTagEntity->getUuid()->toRfc4122()] = $instrumentTagEntity;
            }
        }

        foreach ($targetTagUuids as $uuid) {
            $instrumentTagEntity = $targetTagEntitiesByUuid[$uuid] ?? null;

            if (!$instrumentTagEntity instanceof InstrumentTagEntity) {
                throw new InvalidArgumentException(sprintf('Unknown instrument tag "%s".', $uuid));
            }

            $instrumentEntity->addTag($instrumentTagEntity);
        }
    }

    private function applyCatalogCriteria(QueryBuilder $queryBuilder, InstrumentCatalogCriteria $criteria): void
    {
        $query = trim($criteria->query);

        if ('' !== $query) {
            $queryBuilder
                ->andWhere('
                    LOWER(instrument.name) LIKE LOWER(:query)
                    OR LOWER(COALESCE(instrument.tuning, \'\')) LIKE LOWER(:query)
                    OR LOWER(COALESCE(instrument.notes, \'\')) LIKE LOWER(:query)
                    OR LOWER(COALESCE(tag.label, \'\')) LIKE LOWER(:query)
                ')
                ->setParameter('query', '%' . $query . '%');
        }

        if ('active' === $criteria->activeFilter) {
            $queryBuilder
                ->andWhere('instrument.active = :active')
                ->setParameter('active', true);
        } elseif ('inactive' === $criteria->activeFilter) {
            $queryBuilder
                ->andWhere('instrument.active = :active')
                ->setParameter('active', false);
        }

        if ('with' === $criteria->quantityFilter) {
            $queryBuilder->andWhere('instrument.quantity IS NOT NULL');
        } elseif ('without' === $criteria->quantityFilter) {
            $queryBuilder->andWhere('instrument.quantity IS NULL');
        }

        if ('with' === $criteria->tuningFilter) {
            $queryBuilder
                ->andWhere('instrument.tuning IS NOT NULL')
                ->andWhere('instrument.tuning <> :emptyTuning')
                ->setParameter('emptyTuning', '');
        } elseif ('without' === $criteria->tuningFilter) {
            $queryBuilder
                ->andWhere('(instrument.tuning IS NULL OR instrument.tuning = :emptyTuning)')
                ->setParameter('emptyTuning', '');
        }

        $normalizedTagUuids = array_values(array_unique(array_filter(
            array_map('trim', $criteria->tagUuids),
            static fn (string $uuid): bool => Uuid::isValid($uuid),
        )));

        if ([] !== $normalizedTagUuids) {
            $queryBuilder
                ->innerJoin('instrument.tags', 'filterTag', 'WITH', 'filterTag.uuid IN (:tagUuids)')
                ->setParameter('tagUuids', array_map(static fn (string $uuid): Uuid => Uuid::fromString($uuid), $normalizedTagUuids))
                ->having('COUNT(DISTINCT filterTag.id) = :tagCount')
                ->setParameter('tagCount', count($normalizedTagUuids));
        }
    }

    private function applyCatalogSorting(QueryBuilder $queryBuilder, InstrumentCatalogCriteria $criteria): void
    {
        $field = match ($criteria->sortBy) {
            'active' => 'sort_active',
            'quantity' => 'sort_quantity',
            'tuning' => 'sort_tuning',
            'updatedAt' => 'sort_updated_at',
            default => 'sort_name',
        };
        $direction = 'desc' === strtolower($criteria->sortDirection) ? 'DESC' : 'ASC';

        $queryBuilder
            ->orderBy($field, $direction)
            ->addOrderBy('sort_name', 'ASC')
            ->addOrderBy('id', 'ASC');
    }
}
