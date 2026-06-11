<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Geo\Department;
use App\Domain\Model\ValueObject\DepartmentCode;
use App\Infrastructure\Doctrine\Entity\DepartmentEntity;

final readonly class DepartmentMapper
{
    public function __construct(private RegionMapper $regionMapper)
    {
    }

    public function toDomain(DepartmentEntity $departmentEntity): Department
    {
        $regionEntity = $departmentEntity->getRegion();

        if (null === $regionEntity) {
            throw new \LogicException('Department entity must be attached to a region.');
        }

        return new Department(
            name: $departmentEntity->getName(),
            code: new DepartmentCode($departmentEntity->getCode()),
            region: $this->regionMapper->toDomain($regionEntity),
            uuid: $departmentEntity->getUuid(),
            id: $departmentEntity->getId(),
        );
    }

    public function toEntity(Department $department, ?DepartmentEntity $departmentEntity = null): DepartmentEntity
    {
        $departmentEntity ??= new DepartmentEntity();

        $departmentEntity
            ->setUuid($department->getUuid())
            ->setName($department->getName())
            ->setCode($department->getCode()->value())
            ->setRegion($this->regionMapper->toEntity($department->getRegion(), $departmentEntity->getRegion()));

        return $departmentEntity;
    }
}
