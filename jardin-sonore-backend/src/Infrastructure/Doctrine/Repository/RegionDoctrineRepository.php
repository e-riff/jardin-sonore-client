<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Geo\Region;
use App\Domain\Model\ValueObject\RegionCode;
use App\Domain\Repository\RegionRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\RegionEntity;
use App\Infrastructure\Doctrine\Mapper\RegionMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class RegionDoctrineRepository implements RegionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RegionMapper $regionMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?Region
    {
        $regionEntity = $this->entityManager->getRepository(RegionEntity::class)->findOneBy(['uuid' => $uuid]);

        return $regionEntity instanceof RegionEntity ? $this->regionMapper->toDomain($regionEntity) : null;
    }

    public function findByCode(RegionCode $code): ?Region
    {
        $regionEntity = $this->entityManager->getRepository(RegionEntity::class)->findOneBy(['code' => $code->value()]);

        return $regionEntity instanceof RegionEntity ? $this->regionMapper->toDomain($regionEntity) : null;
    }

    public function save(Region $region): void
    {
        $regionEntity = $this->entityManager->getRepository(RegionEntity::class)->findOneBy(['uuid' => $region->getUuid()]);

        $this->entityManager->persist($this->regionMapper->toEntity(
            region: $region,
            regionEntity: $regionEntity instanceof RegionEntity ? $regionEntity : null,
        ));
        $this->entityManager->flush();
    }
}
