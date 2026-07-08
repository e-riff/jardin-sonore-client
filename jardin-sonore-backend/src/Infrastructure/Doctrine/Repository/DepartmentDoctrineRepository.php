<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Geo\Department;
use App\Domain\Model\ValueObject\DepartmentCode;
use App\Domain\Repository\DepartmentRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\DepartmentEntity;
use App\Infrastructure\Doctrine\Mapper\DepartmentMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<DepartmentEntity>
 */
final class DepartmentDoctrineRepository extends ServiceEntityRepository implements DepartmentRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly DepartmentMapper $departmentMapper,
    ) {
        parent::__construct($managerRegistry, DepartmentEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?Department
    {
        $departmentEntity = $this->findOneBy(['uuid' => $uuid]);

        return $departmentEntity instanceof DepartmentEntity ? $this->departmentMapper->toDomain($departmentEntity) : null;
    }

    public function findByCode(DepartmentCode $code): ?Department
    {
        $departmentEntity = $this->findOneBy(['code' => $code->value()]);

        return $departmentEntity instanceof DepartmentEntity ? $this->departmentMapper->toDomain($departmentEntity) : null;
    }

    public function save(Department $department): void
    {
        $departmentEntity = $this->findOneBy(['uuid' => $department->getUuid()]);

        $this->getEntityManager()->persist($this->departmentMapper->toEntity(
            department: $department,
            departmentEntity: $departmentEntity instanceof DepartmentEntity ? $departmentEntity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
