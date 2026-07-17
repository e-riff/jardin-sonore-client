<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;
use App\Infrastructure\Doctrine\Mapper\RepertoireItemMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
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
        $this->getEntityManager()->persist($this->repertoireItemMapper->toEntity($repertoireItem, $entity instanceof RepertoireItemEntity ? $entity : null));
        $this->getEntityManager()->flush();
    }
}
