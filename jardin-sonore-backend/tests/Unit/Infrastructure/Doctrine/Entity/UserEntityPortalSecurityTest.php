<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\UserEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserEntityPortalSecurityTest extends TestCase
{
    public function testItProvidesAPortalSecurityIdentity(): void
    {
        $userEntity = (new UserEntity())->setEmail('structure@example.test');

        self::assertInstanceOf(UserInterface::class, $userEntity);
        self::assertSame('structure@example.test', $userEntity->getUserIdentifier());
        self::assertSame(['ROLE_PORTAL_USER'], $userEntity->getRoles());
    }
}
