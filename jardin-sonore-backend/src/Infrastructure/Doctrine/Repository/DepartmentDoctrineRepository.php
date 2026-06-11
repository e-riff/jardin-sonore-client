<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Geo\Department;
use App\Domain\Model\ValueObject\DepartmentCode;
use App\Domain\Repository\DepartmentRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\DepartmentEntity;
use App\Infrastructure\Doctrine\Mapper\DepartmentMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DepartmentDoctrineRepository implements DepartmentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentMapper $departmentMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?Department
    {
        $departmentEntity = $this->entityManager->getRepository(DepartmentEntity::class)->findOneBy(['uuid' => $uuid]);

        return $departmentEntity instanceof DepartmentEntity ? $this->departmentMapper->toDomain($departmentEntity) : null;
    }

    public function findByCode(DepartmentCode $code): ?Department
    {
        $departmentEntity = $this->entityManager->getRepository(DepartmentEntity::class)->findOneBy(['code' => $code->value()]);

        return $departmentEntity instanceof DepartmentEntity ? $this->departmentMapper->toDomain($departmentEntity) : null;
    }

    public function save(Department $department): void
    {
        $departmentEntity = $this->entityManager->getRepository(DepartmentEntity::class)->findOneBy(['uuid' => $department->getUuid()]);

        $this->entityManager->persist($this->departmentMapper->toEntity(
            department: $department,
            departmentEntity: $departmentEntity instanceof DepartmentEntity ? $departmentEntity : null,
        ));
        $this->entityManager->flush();
    }
}
