<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Geo\Region;
use App\Domain\Model\ValueObject\RegionCode;
use App\Domain\Repository\RegionRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\RegionEntity;
use App\Infrastructure\Doctrine\Mapper\RegionMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<RegionEntity>
 */
final class RegionDoctrineRepository extends ServiceEntityRepository implements RegionRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly RegionMapper $regionMapper,
    ) {
        parent::__construct($managerRegistry, RegionEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?Region
    {
        $regionEntity = $this->findOneBy(['uuid' => $uuid]);

        return $regionEntity instanceof RegionEntity ? $this->regionMapper->toDomain($regionEntity) : null;
    }

    public function findByCode(RegionCode $code): ?Region
    {
        $regionEntity = $this->findOneBy(['code' => $code->value()]);

        return $regionEntity instanceof RegionEntity ? $this->regionMapper->toDomain($regionEntity) : null;
    }

    public function save(Region $region): void
    {
        $regionEntity = $this->findOneBy(['uuid' => $region->getUuid()]);

        $this->getEntityManager()->persist($this->regionMapper->toEntity(
            region: $region,
            regionEntity: $regionEntity instanceof RegionEntity ? $regionEntity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
