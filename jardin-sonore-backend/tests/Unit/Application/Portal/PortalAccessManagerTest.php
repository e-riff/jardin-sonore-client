<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Portal;

use App\Application\Portal\PortalAccessManager;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use LogicException;
use PHPUnit\Framework\TestCase;

final class PortalAccessManagerTest extends TestCase
{
    public function testItRemovesAnAccessWhenTheAccountRetainsAnotherOne(): void
    {
        $userEntity = new UserEntity();
        $firstUserOrganizationAccessEntity = new UserOrganizationAccessEntity();
        $secondUserOrganizationAccessEntity = new UserOrganizationAccessEntity();
        $userEntity->addOrganizationAccess($firstUserOrganizationAccessEntity);
        $userEntity->addOrganizationAccess($secondUserOrganizationAccessEntity);

        (new PortalAccessManager())->removeAccess($userEntity, $firstUserOrganizationAccessEntity);

        self::assertCount(1, $userEntity->getOrganizationAccesses());
        self::assertTrue($userEntity->getOrganizationAccesses()->contains($secondUserOrganizationAccessEntity));
    }

    public function testItRejectsRemovalOfTheLastAccess(): void
    {
        $userEntity = new UserEntity();
        $userOrganizationAccessEntity = new UserOrganizationAccessEntity();
        $userEntity->addOrganizationAccess($userOrganizationAccessEntity);

        $this->expectException(LogicException::class);

        (new PortalAccessManager())->removeAccess($userEntity, $userOrganizationAccessEntity);
    }
}
