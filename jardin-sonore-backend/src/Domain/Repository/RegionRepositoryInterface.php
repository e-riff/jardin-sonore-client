<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Geo\Region;
use App\Domain\Model\ValueObject\RegionCode;
use Symfony\Component\Uid\Uuid;

interface RegionRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?Region;

    public function findByCode(RegionCode $code): ?Region;

    public function save(Region $region): void;
}
