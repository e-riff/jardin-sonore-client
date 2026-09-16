<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Session;

use App\Domain\Model\AddressBook\Organization;
use App\Domain\Model\Session\SessionSummary;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SessionSummaryOrganizationTest extends TestCase
{
    public function testOrganizationsAssociationIsOptionalAndCanBeDetached(): void
    {
        $organization = new Organization('Crèche des Lilas');
        $secondOrganization = new Organization('Médiathèque municipale');
        $sessionSummary = new SessionSummary('Matin musical', new DateTimeImmutable('2026-09-04'), [$organization, $secondOrganization]);

        self::assertSame([$organization, $secondOrganization], $sessionSummary->getOrganizations());
        $sessionSummary->updateDetails('Matin musical', new DateTimeImmutable('2026-09-04'), [], null, null, null, null, []);
        self::assertSame([], $sessionSummary->getOrganizations());
    }
}
