<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Portal;

use App\Domain\Model\AddressBook\Organization;
use App\Domain\Model\AddressBook\Person;
use App\Domain\Model\Portal\User;
use App\Domain\Model\Portal\UserOrganizationAccess;
use App\Domain\Model\ValueObject\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserOrganizationAccessTest extends TestCase
{
    public function testNewUserIsPendingAndCanHaveOneAccessPerOrganization(): void
    {
        $organization = new Organization('Crèche des Lilas');
        $user = new User(new EmailAddress('contact@example.test'));

        $user->grantOrganizationAccess($organization);

        self::assertTrue($user->isPending());
        self::assertCount(1, $user->getOrganizationAccesses());
        $this->expectException(InvalidArgumentException::class);
        $user->grantOrganizationAccess($organization);
    }

    public function testAccessRejectsPersonFromAnotherOrganization(): void
    {
        $organization = new Organization('Crèche des Lilas');
        $otherOrganization = new Organization('Médiathèque');
        $person = new Person('Anne', 'Martin', $otherOrganization);

        $this->expectException(InvalidArgumentException::class);
        new UserOrganizationAccess(new User(new EmailAddress('anne@example.test')), $organization, $person);
    }
}
