<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Administration\AdminUser;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;

final readonly class AdminUserMapper
{
    public function toDomain(AdminUserEntity $adminUserEntity): AdminUser
    {
        return new AdminUser(
            emailAddress: new EmailAddress($adminUserEntity->getEmail()),
            passwordHash: $adminUserEntity->getPassword(),
            active: $adminUserEntity->isActive(),
            uuid: $adminUserEntity->getUuid(),
            id: $adminUserEntity->getId(),
        );
    }

    public function toEntity(AdminUser $adminUser, ?AdminUserEntity $adminUserEntity = null): AdminUserEntity
    {
        $adminUserEntity ??= new AdminUserEntity();

        $adminUserEntity
            ->setUuid($adminUser->getUuid())
            ->setEmail($adminUser->getEmailAddress()->value())
            ->setPassword($adminUser->getPasswordHash())
            ->setActive($adminUser->isActive());

        return $adminUserEntity;
    }
}
