<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Directory;

use App\Application\Directory\DirectoryEstablishmentMatch;
use App\Application\Directory\DirectoryEstablishmentMatcher;
use App\Application\Directory\DirectoryEstablishmentScorer;
use App\Application\Directory\DirectoryOrganizationCandidateLookupInterface;
use App\Application\Directory\DirectoryOrganizationLookupInterface;
use App\Application\Directory\DirectoryOrganizationTypeResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DirectoryEstablishmentMatcherTest extends TestCase
{
    #[Test]
    public function itRejectsAutomaticMatchingWhenTheTwoBestCandidatesAreTooClose(): void
    {
        $matcher = new DirectoryEstablishmentMatcher(
            $this->createStub(DirectoryOrganizationCandidateLookupInterface::class),
            $this->createStub(DirectoryOrganizationLookupInterface::class),
            new DirectoryEstablishmentScorer(new DirectoryOrganizationTypeResolver()),
        );

        self::assertFalse($matcher->isConfidentAutoMatch([
            $this->match(97, 10),
            $this->match(84, 20),
        ]));
        self::assertTrue($matcher->isConfidentAutoMatch([
            $this->match(97, 10),
            $this->match(80, 20),
        ]));
        self::assertTrue($matcher->isConfidentAutoMatch([$this->match(91, 10)]));
        self::assertFalse($matcher->isConfidentAutoMatch([$this->match(90, 10)]));
    }

    private function match(int $score, int $organizationId): DirectoryEstablishmentMatch
    {
        return new DirectoryEstablishmentMatch(
            organizationId: $organizationId,
            organizationName: 'Organization ' . $organizationId,
            score: $score,
            email: '',
            phone: '',
            commune: '',
            address: '',
            website: '',
        );
    }
}
