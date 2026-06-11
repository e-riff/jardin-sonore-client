<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Geo\Region;
use App\Domain\Model\ValueObject\RegionCode;
use App\Infrastructure\Doctrine\Entity\RegionEntity;

final class RegionMapper
{
    public function toDomain(RegionEntity $regionEntity): Region
    {
        return new Region(
            name: $regionEntity->getName(),
            code: new RegionCode($regionEntity->getCode()),
            uuid: $regionEntity->getUuid(),
            id: $regionEntity->getId(),
        );
    }

    public function toEntity(Region $region, ?RegionEntity $regionEntity = null): RegionEntity
    {
        $regionEntity ??= new RegionEntity();

        $regionEntity
            ->setUuid($region->getUuid())
            ->setName($region->getName())
            ->setCode($region->getCode()->value());

        return $regionEntity;
    }
}
