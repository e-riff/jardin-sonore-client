<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\ContentCatalog\Instrument;
use App\Domain\Model\ContentCatalog\InstrumentTag;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\InstrumentEntity;
use App\Infrastructure\Doctrine\Entity\InstrumentTagEntity;
use App\Infrastructure\Doctrine\Mapper\InstrumentMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<InstrumentEntity>
 */
final class InstrumentDoctrineRepository extends ServiceEntityRepository implements InstrumentRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly InstrumentMapper $instrumentMapper,
        private readonly InstrumentTagEntityRepository $instrumentTagEntityRepository,
    ) {
        parent::__construct($managerRegistry, InstrumentEntity::class);
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

        $targetTagEntities = $this->instrumentTagEntityRepository->findByUuids($targetTagUuids);

        $targetTagEntitiesByUuid = [];

        foreach ($targetTagEntities as $instrumentTagEntity) {
            $targetTagEntitiesByUuid[$instrumentTagEntity->getUuid()->toRfc4122()] = $instrumentTagEntity;
        }

        foreach ($targetTagUuids as $uuid) {
            $instrumentTagEntity = $targetTagEntitiesByUuid[$uuid] ?? null;

            if (!$instrumentTagEntity instanceof InstrumentTagEntity) {
                throw new InvalidArgumentException(sprintf('Unknown instrument tag "%s".', $uuid));
            }

            $instrumentEntity->addTag($instrumentTagEntity);
        }
    }
}
