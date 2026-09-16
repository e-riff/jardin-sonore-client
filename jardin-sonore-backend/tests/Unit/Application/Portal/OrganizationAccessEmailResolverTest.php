<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Portal;

use App\Application\Portal\OrganizationAccessEmailResolver;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OrganizationAccessEmailResolverTest extends TestCase
{
    public function testItResolvesAnOrganizationEmailWithoutPerson(): void
    {
        $organizationEntity = new OrganizationEntity();
        $organizationEntity->setName('Crèche des Lilas');
        $this->linkEmail($organizationEntity, 'contact@lilas.test');

        $organizationAccessEmailSelection = (new OrganizationAccessEmailResolver())->resolve(
            $organizationEntity,
            'CONTACT@LILAS.TEST',
        );

        self::assertSame('contact@lilas.test', $organizationAccessEmailSelection->emailAddress);
        self::assertNull($organizationAccessEmailSelection->personEntity);
    }

    public function testItResolvesAPersonEmailFromTheSelectedOrganization(): void
    {
        $organizationEntity = new OrganizationEntity();
        $personEntity = (new PersonEntity())->setFirstName('Anne')->setLastName('Martin');
        $organizationEntity->addPerson($personEntity);
        $this->linkEmail($personEntity, 'anne.martin@lilas.test');

        $organizationAccessEmailSelection = (new OrganizationAccessEmailResolver())->resolve(
            $organizationEntity,
            'anne.martin@lilas.test',
        );

        self::assertSame('anne.martin@lilas.test', $organizationAccessEmailSelection->emailAddress);
        self::assertSame($personEntity, $organizationAccessEmailSelection->personEntity);
    }

    public function testItRejectsAnEmailNotLinkedToTheSelectedOrganization(): void
    {
        $organizationEntity = new OrganizationEntity();
        $otherOrganizationEntity = new OrganizationEntity();
        $this->linkEmail($otherOrganizationEntity, 'contact@other.test');

        $this->expectException(InvalidArgumentException::class);

        (new OrganizationAccessEmailResolver())->resolve($organizationEntity, 'contact@other.test');
    }

    public function testItRejectsAnInactiveOrganizationEmail(): void
    {
        $organizationEntity = new OrganizationEntity();
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress('inactive@lilas.test')->setActive(false);
        $emailContactLinkEntity = (new EmailContactLinkEntity())->setEmailContact($emailContactEntity);
        $organizationEntity->getContactDetails()?->addEmailContactLink($emailContactLinkEntity);

        $this->expectException(InvalidArgumentException::class);

        (new OrganizationAccessEmailResolver())->resolve($organizationEntity, 'inactive@lilas.test');
    }

    public function testItRejectsAnEmailWithAnInactiveOrganizationLink(): void
    {
        $organizationEntity = new OrganizationEntity();
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress('inactive-link@lilas.test');
        $emailContactLinkEntity = (new EmailContactLinkEntity())->setEmailContact($emailContactEntity)->setActive(false);
        $organizationEntity->getContactDetails()?->addEmailContactLink($emailContactLinkEntity);

        $this->expectException(InvalidArgumentException::class);

        (new OrganizationAccessEmailResolver())->resolve($organizationEntity, 'inactive-link@lilas.test');
    }

    private function linkEmail(OrganizationEntity|PersonEntity $directoryEntryEntity, string $emailAddress): void
    {
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress($emailAddress);
        $emailContactLinkEntity = (new EmailContactLinkEntity())->setEmailContact($emailContactEntity);

        $directoryEntryEntity->getContactDetails()?->addEmailContactLink($emailContactLinkEntity);
    }
}
