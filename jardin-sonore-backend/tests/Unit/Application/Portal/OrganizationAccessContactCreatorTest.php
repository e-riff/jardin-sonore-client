<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Portal;

use App\Application\Portal\OrganizationAccessContactCreator;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use PHPUnit\Framework\TestCase;

final class OrganizationAccessContactCreatorTest extends TestCase
{
    public function testItAddsANewEmailToTheOrganization(): void
    {
        $organizationEntity = new OrganizationEntity();

        (new OrganizationAccessContactCreator())->addOrganizationEmail($organizationEntity, 'contact@lilas.test');

        self::assertSame('contact@lilas.test', $organizationEntity->getContactDetails()?->getEmailContacts()->first()?->getEmailAddress());
        self::assertCount(0, $organizationEntity->getPeople());
    }

    public function testItAddsAPersonWithTheNewEmailToTheOrganization(): void
    {
        $organizationEntity = new OrganizationEntity();

        $personEntity = (new OrganizationAccessContactCreator())->addPersonWithEmail(
            $organizationEntity,
            'Anne',
            'Martin',
            'anne.martin@lilas.test',
        );

        self::assertSame($organizationEntity, $personEntity->getOrganization());
        self::assertSame('Anne Martin', (string) $personEntity);
        self::assertSame('anne.martin@lilas.test', $personEntity->getContactDetails()?->getEmailContacts()->first()?->getEmailAddress());
    }
}
