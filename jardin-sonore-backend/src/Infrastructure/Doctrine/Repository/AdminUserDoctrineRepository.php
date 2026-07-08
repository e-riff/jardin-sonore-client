<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Administration\AdminUser;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\AdminUserRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Mapper\AdminUserMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<AdminUserEntity>
 */
final class AdminUserDoctrineRepository extends ServiceEntityRepository implements AdminUserRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly AdminUserMapper $adminUserMapper,
    ) {
        parent::__construct($managerRegistry, AdminUserEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?AdminUser
    {
        $adminUserEntity = $this->findOneBy(['uuid' => $uuid]);

        return $adminUserEntity instanceof AdminUserEntity ? $this->adminUserMapper->toDomain($adminUserEntity) : null;
    }

    public function findByEmailAddress(EmailAddress $emailAddress): ?AdminUser
    {
        $adminUserEntity = $this->findOneBy([
            'email' => mb_strtolower($emailAddress->value()),
        ]);

        return $adminUserEntity instanceof AdminUserEntity ? $this->adminUserMapper->toDomain($adminUserEntity) : null;
    }

    public function save(AdminUser $adminUser): void
    {
        $adminUserEntity = $this->findOneBy(['uuid' => $adminUser->getUuid()]);

        $this->getEntityManager()->persist($this->adminUserMapper->toEntity(
            adminUser: $adminUser,
            adminUserEntity: $adminUserEntity instanceof AdminUserEntity ? $adminUserEntity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
