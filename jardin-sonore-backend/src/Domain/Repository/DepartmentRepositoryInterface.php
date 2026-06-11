<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Geo\Department;
use App\Domain\Model\ValueObject\DepartmentCode;
use Symfony\Component\Uid\Uuid;

interface DepartmentRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?Department;

    public function findByCode(DepartmentCode $code): ?Department;

    public function save(Department $department): void;
}
