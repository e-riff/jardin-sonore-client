<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Administration\AdminUser;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\AdminUserRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Mapper\AdminUserMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AdminUserDoctrineRepository implements AdminUserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminUserMapper $adminUserMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?AdminUser
    {
        $adminUserEntity = $this->entityManager->getRepository(AdminUserEntity::class)->findOneBy(['uuid' => $uuid]);

        return $adminUserEntity instanceof AdminUserEntity ? $this->adminUserMapper->toDomain($adminUserEntity) : null;
    }

    public function findByEmailAddress(EmailAddress $emailAddress): ?AdminUser
    {
        $adminUserEntity = $this->entityManager->getRepository(AdminUserEntity::class)->findOneBy([
            'email' => mb_strtolower($emailAddress->value()),
        ]);

        return $adminUserEntity instanceof AdminUserEntity ? $this->adminUserMapper->toDomain($adminUserEntity) : null;
    }

    public function save(AdminUser $adminUser): void
    {
        $adminUserEntity = $this->entityManager->getRepository(AdminUserEntity::class)->findOneBy(['uuid' => $adminUser->getUuid()]);

        $this->entityManager->persist($this->adminUserMapper->toEntity(
            adminUser: $adminUser,
            adminUserEntity: $adminUserEntity instanceof AdminUserEntity ? $adminUserEntity : null,
        ));
        $this->entityManager->flush();
    }
}
