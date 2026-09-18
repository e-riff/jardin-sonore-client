<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use PHPUnit\Framework\TestCase;

final class UserEntityTest extends TestCase
{
    public function testItKeepsAccessesManagedFromThePortalAccount(): void
    {
        $userEntity = new UserEntity();
        $userOrganizationAccessEntity = new UserOrganizationAccessEntity();

        $userEntity->addOrganizationAccess($userOrganizationAccessEntity);

        self::assertSame($userEntity, $userOrganizationAccessEntity->getUser());
        self::assertCount(1, $userEntity->getOrganizationAccesses());
        $userEntity->removeOrganizationAccess($userOrganizationAccessEntity);
        self::assertCount(0, $userEntity->getOrganizationAccesses());
    }
}
