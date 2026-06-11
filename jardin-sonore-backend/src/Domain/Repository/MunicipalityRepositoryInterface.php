<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Geo\Municipality;
use App\Domain\Model\ValueObject\InseeCode;
use Symfony\Component\Uid\Uuid;

interface MunicipalityRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?Municipality;

    public function findByInseeCode(InseeCode $inseeCode): ?Municipality;

    public function save(Municipality $municipality): void;
}
