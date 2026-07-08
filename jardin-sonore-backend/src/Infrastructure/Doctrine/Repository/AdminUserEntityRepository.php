<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminUserEntity>
 */
final class AdminUserEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, AdminUserEntity::class);
    }

    public function findOneByEmailAddress(string $emailAddress): ?AdminUserEntity
    {
        $adminUser = $this->findOneBy(['email' => $emailAddress]);

        return $adminUser instanceof AdminUserEntity ? $adminUser : null;
    }
}
