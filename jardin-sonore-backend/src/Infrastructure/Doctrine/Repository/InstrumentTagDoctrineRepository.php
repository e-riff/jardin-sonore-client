<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\ContentCatalog\InstrumentTag;
use App\Domain\Repository\InstrumentTagRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\InstrumentTagEntity;
use App\Infrastructure\Doctrine\Mapper\InstrumentTagMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class InstrumentTagDoctrineRepository implements InstrumentTagRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InstrumentTagMapper $instrumentTagMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?InstrumentTag
    {
        $entity = $this->entityManager->getRepository(InstrumentTagEntity::class)->findOneBy(['uuid' => $uuid]);

        return $entity instanceof InstrumentTagEntity ? $this->instrumentTagMapper->toDomain($entity) : null;
    }

    public function findByLabel(string $label): ?InstrumentTag
    {
        $entity = $this->entityManager->getRepository(InstrumentTagEntity::class)->findOneBy(['label' => trim($label)]);

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

        $entities = $this->entityManager->getRepository(InstrumentTagEntity::class)
            ->createQueryBuilder('instrumentTag')
            ->andWhere('instrumentTag.uuid IN (:uuids)')
            ->setParameter('uuids', array_map(static fn (string $uuid): Uuid => Uuid::fromString($uuid), $normalizedUuids))
            ->getQuery()
            ->getResult();

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

    public function findAllOrderedByLabel(): array
    {
        $entities = $this->entityManager->getRepository(InstrumentTagEntity::class)
            ->createQueryBuilder('instrumentTag')
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
        $entity = $this->entityManager->getRepository(InstrumentTagEntity::class)
            ->findOneBy(['uuid' => $instrumentTag->getUuid()]);

        $this->entityManager->persist($this->instrumentTagMapper->toEntity(
            instrumentTag: $instrumentTag,
            instrumentTagEntity: $entity instanceof InstrumentTagEntity ? $entity : null,
        ));
        $this->entityManager->flush();
    }
}
