<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Portal\PortalListCriteria;
use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use App\Infrastructure\Doctrine\Mapper\RepertoireItemMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<RepertoireItemEntity>
 */
final class RepertoireItemDoctrineRepository extends ServiceEntityRepository implements RepertoireItemRepositoryInterface
{
    public function __construct(ManagerRegistry $managerRegistry, private readonly RepertoireItemMapper $repertoireItemMapper)
    {
        parent::__construct($managerRegistry, RepertoireItemEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?RepertoireItem
    {
        $entity = $this->findOneBy(['uuid' => $uuid]);

        return $entity instanceof RepertoireItemEntity ? $this->repertoireItemMapper->toDomain($entity) : null;
    }

    /**
     * @param list<string> $sourceUuids
     *
     * @return array{items: list<RepertoireItemEntity>, total: int, page: int, pageSize: int}
     */
    public function paginatedPortalItems(array $sourceUuids, PortalListCriteria $criteria): array
    {
        $pageSize = 20;
        if ([] === $sourceUuids) {
            return ['items' => [], 'total' => 0, 'page' => $criteria->page, 'pageSize' => $pageSize];
        }

        $queryBuilder = $this->createQueryBuilder('item')
            ->andWhere('item.active = :active')
            ->andWhere('item.type IN (:types)')
            ->setParameter('active', true)
            ->setParameter('types', [RepertoireItemType::NURSERY_RHYME->value, RepertoireItemType::FINGERPLAY->value]);
        $sourceCondition = $queryBuilder->expr()->orX();
        foreach ($sourceUuids as $index => $sourceUuid) {
            $parameterName = "sourceUuid{$index}";
            $sourceCondition->add("item.uuid = :{$parameterName}");
            $queryBuilder->setParameter($parameterName, Uuid::fromString($sourceUuid), UuidType::NAME);
        }
        $queryBuilder->andWhere($sourceCondition);
        if (null !== $criteria->type) {
            $queryBuilder->andWhere('item.type = :type')->setParameter('type', $criteria->type);
        }
        if (null !== $criteria->query) {
            $queryBuilder
                ->leftJoin('item.themes', 'searchTheme')
                ->andWhere($queryBuilder->expr()->orX('LOWER(item.title) LIKE :query', 'LOWER(searchTheme.label) LIKE :query'))
                ->setParameter('query', '%' . mb_strtolower($criteria->query) . '%');
        }
        if ([] !== $criteria->themeUuids) {
            $queryBuilder->innerJoin('item.themes', 'filterTheme');
            $themeCondition = $queryBuilder->expr()->orX();
            foreach ($criteria->themeUuids as $index => $themeUuid) {
                $parameterName = "themeUuid{$index}";
                $themeCondition->add("filterTheme.uuid = :{$parameterName}");
                $queryBuilder->setParameter($parameterName, Uuid::fromString($themeUuid), UuidType::NAME);
            }
            $queryBuilder->andWhere($themeCondition);
        }
        $total = (int) (clone $queryBuilder)->select('COUNT(DISTINCT item.id)')->getQuery()->getSingleScalarResult();
        $queryBuilder
            ->select('DISTINCT item')
            ->orderBy('title' === $criteria->sort ? 'item.title' : 'item.updatedAt', strtoupper($criteria->direction))
            ->addOrderBy('item.id', 'DESC')
            ->setFirstResult(($criteria->page - 1) * $pageSize)
            ->setMaxResults($pageSize);

        return ['items' => $queryBuilder->getQuery()->getResult(), 'total' => $total, 'page' => $criteria->page, 'pageSize' => $pageSize];
    }

    /**
     * @param list<string> $sourceUuids
     *
     * @return list<array{uuid: Uuid|string, label: string, color: string}>
     */
    public function portalThemes(array $sourceUuids): array
    {
        if ([] === $sourceUuids) {
            return [];
        }
        $queryBuilder = $this->createQueryBuilder('item')
            ->select('DISTINCT theme.uuid AS uuid, theme.label AS label, theme.color AS color')
            ->innerJoin('item.themes', 'theme')
            ->andWhere('item.active = :active')
            ->andWhere('item.type IN (:types)')
            ->setParameter('active', true)
            ->setParameter('types', [RepertoireItemType::NURSERY_RHYME->value, RepertoireItemType::FINGERPLAY->value])
            ->orderBy('theme.label', 'ASC');
        $sourceCondition = $queryBuilder->expr()->orX();
        foreach ($sourceUuids as $index => $sourceUuid) {
            $parameterName = "sourceUuid{$index}";
            $sourceCondition->add("item.uuid = :{$parameterName}");
            $queryBuilder->setParameter($parameterName, Uuid::fromString($sourceUuid), UuidType::NAME);
        }

        return $queryBuilder->andWhere($sourceCondition)->getQuery()->getResult();
    }

    public function search(?RepertoireItemType $repertoireItemType = null, ?string $query = null, bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('item')
            ->orderBy('item.updatedAt', 'DESC')
            ->addOrderBy('item.id', 'DESC');
        if (null !== $repertoireItemType) {
            $qb->andWhere('item.type = :type')->setParameter('type', $repertoireItemType);
        }
        if (null !== $query && '' !== trim($query)) {
            $qb
                ->andWhere('
                    LOWER(item.title) LIKE LOWER(:query)
                    OR LOWER(COALESCE(item.source, \'\')) LIKE LOWER(:query)
                    OR LOWER(item.body) LIKE LOWER(:query)
                    OR LOWER(COALESCE(item.notes, \'\')) LIKE LOWER(:query)
                ')
                ->setParameter('query', '%' . trim($query) . '%');
        }
        if ($activeOnly) {
            $qb->andWhere('item.active = :active')->setParameter('active', true);
        }

        return array_map(fn ($entity): RepertoireItem => $this->repertoireItemMapper->toDomain($entity), $qb->getQuery()->getResult());
    }

    public function save(RepertoireItem $repertoireItem): void
    {
        $entity = $this->findOneBy(['uuid' => $repertoireItem->getUuid()]);
        $repertoireItemEntity = $this->repertoireItemMapper->toEntity($repertoireItem, $entity instanceof RepertoireItemEntity ? $entity : null);
        $this->syncThemes($repertoireItemEntity, $repertoireItem->getThemes());
        $this->getEntityManager()->persist($repertoireItemEntity);
        $this->getEntityManager()->flush();
    }

    /** @param list<\App\Domain\Model\ContentCatalog\Theme> $themes */
    private function syncThemes(RepertoireItemEntity $repertoireItemEntity, array $themes): void
    {
        $wantedUuids = array_map(static fn ($theme): string => $theme->getUuid()->toRfc4122(), $themes);
        foreach ($repertoireItemEntity->getThemes()->toArray() as $themeEntity) {
            if (!in_array($themeEntity->getUuid()->toRfc4122(), $wantedUuids, true)) {
                $repertoireItemEntity->removeTheme($themeEntity);
            }
        }
        foreach ($wantedUuids as $uuid) {
            $themeEntity = $this->getEntityManager()->getRepository(ThemeEntity::class)->findOneBy(['uuid' => $uuid]);
            if ($themeEntity instanceof ThemeEntity) {
                $repertoireItemEntity->addTheme($themeEntity);
            }
        }
    }

    public function delete(RepertoireItem $repertoireItem): void
    {
        $entity = $this->findOneBy(['uuid' => $repertoireItem->getUuid()]);

        if (!$entity instanceof RepertoireItemEntity) {
            return;
        }

        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }
}
