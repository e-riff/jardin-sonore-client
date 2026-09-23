<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Admin;

use App\Application\Portal\PortalAccessManager;
use App\Infrastructure\Admin\UserOrganizationAccessCrudController;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\TestCase;

final class UserOrganizationAccessCrudControllerTest extends TestCase
{
    public function testItUsesTheOrganizationNameAsItsAdministrativeLabel(): void
    {
        $organizationEntity = (new OrganizationEntity())->setName('Crèche des Lilas');
        $userOrganizationAccessEntity = (new UserOrganizationAccessEntity())->setOrganization($organizationEntity);

        self::assertSame('Crèche des Lilas', (string) $userOrganizationAccessEntity);
    }

    public function testItAddsTheEnteredPostalCodeAndCityToItsAdministrativeLabel(): void
    {
        $organizationEntity = (new OrganizationEntity())->setName('Crèche des Lilas');
        $organizationEntity->getContactDetails()?->addAddressContact(
            (new AddressContactEntity())->setPostalCode('42000')->setCity('Saint-Étienne'),
        );
        $userOrganizationAccessEntity = (new UserOrganizationAccessEntity())->setOrganization($organizationEntity);

        self::assertSame('Crèche des Lilas — 42000 — Saint-Étienne', (string) $userOrganizationAccessEntity);
    }

    public function testItRefusesToDeleteTheLastOrganizationAccess(): void
    {
        $userEntity = new UserEntity();
        $userOrganizationAccessEntity = new UserOrganizationAccessEntity();
        $userEntity->addOrganizationAccess($userOrganizationAccessEntity);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');

        $this->expectException(LogicException::class);

        (new UserOrganizationAccessCrudController(new PortalAccessManager()))->deleteEntity($entityManager, $userOrganizationAccessEntity);
    }
}
