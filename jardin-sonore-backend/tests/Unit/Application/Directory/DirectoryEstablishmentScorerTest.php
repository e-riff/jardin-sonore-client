<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Directory;

use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Application\Directory\DirectoryEstablishmentScorer;
use App\Application\Directory\DirectoryOrganizationCandidate;
use App\Application\Directory\DirectoryOrganizationTypeResolver;
use App\Domain\Model\AddressBook\OrganizationType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DirectoryEstablishmentScorerTest extends TestCase
{
    #[Test]
    public function aDifferentTypeWithAnIdenticalNameRequiresManualReview(): void
    {
        $item = DirectoryEstablishmentImportItem::fromArray([
            'id' => 'mjc-rue-des-ecoles',
            'type' => 'MJC',
            'name' => 'MJC des Écoles',
            'commune' => 'Lyon',
            'address' => '4 rue des Écoles, 69003 Lyon',
            'phone' => '04 72 00 00 00',
        ]);
        $candidate = new DirectoryOrganizationCandidate(
            organizationId: 1,
            name: 'MJC des Écoles',
            websiteUrl: null,
            email: '',
            phone: '04 72 00 00 00',
            commune: 'Lyon',
            address: '4 rue des Écoles, 69003 Lyon',
            type: OrganizationType::CRECHE,
        );
        $scorer = new DirectoryEstablishmentScorer(new DirectoryOrganizationTypeResolver());

        self::assertSame(90, $scorer->computeCandidateScore($candidate, $item));
        self::assertGreaterThan(90, $scorer->computeCandidateScore(new DirectoryOrganizationCandidate(
            organizationId: 2,
            name: $candidate->name,
            websiteUrl: $candidate->websiteUrl,
            email: $candidate->email,
            phone: $candidate->phone,
            commune: $candidate->commune,
            address: $candidate->address,
            type: OrganizationType::MJC,
        ), $item));
    }
}
