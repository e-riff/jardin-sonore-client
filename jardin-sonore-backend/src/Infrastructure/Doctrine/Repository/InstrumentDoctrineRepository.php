<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\ContentCatalog\Instrument;
use App\Domain\Model\ContentCatalog\InstrumentTag;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\InstrumentEntity;
use App\Infrastructure\Doctrine\Entity\InstrumentTagEntity;
use App\Infrastructure\Doctrine\Mapper\InstrumentMapper;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class InstrumentDoctrineRepository implements InstrumentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InstrumentMapper $instrumentMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?Instrument
    {
        $entity = $this->entityManager->getRepository(InstrumentEntity::class)->findOneBy(['uuid' => $uuid]);

        return $entity instanceof InstrumentEntity ? $this->instrumentMapper->toDomain($entity) : null;
    }

    public function save(Instrument $instrument): void
    {
        $entity = $this->entityManager->getRepository(InstrumentEntity::class)
            ->findOneBy(['uuid' => $instrument->getUuid()]);

        $instrumentEntity = $this->instrumentMapper->toEntity(
            instrument: $instrument,
            instrumentEntity: $entity instanceof InstrumentEntity ? $entity : null,
        );

        $this->synchronizeTags($instrumentEntity, $instrument->getTags());

        $this->entityManager->persist($instrumentEntity);
        $this->entityManager->flush();
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

        $targetTagEntities = $this->entityManager->getRepository(InstrumentTagEntity::class)
            ->createQueryBuilder('instrumentTag')
            ->andWhere('instrumentTag.uuid IN (:uuids)')
            ->setParameter('uuids', array_map(static fn (string $uuid): Uuid => Uuid::fromString($uuid), $targetTagUuids))
            ->getQuery()
            ->getResult();

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
