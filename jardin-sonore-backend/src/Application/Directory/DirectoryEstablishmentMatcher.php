<?php

declare(strict_types=1);

namespace App\Application\Directory;

final readonly class DirectoryEstablishmentMatcher
{
    private const array MATCH_SCORE_THRESHOLDS = [
        'auto_match' => 90,
        'candidate' => 80,
    ];

    public function __construct(
        private DirectoryOrganizationCandidateLookupInterface $directoryOrganizationCandidateLookup,
        private DirectoryOrganizationLookupInterface $directoryOrganizationLookup,
        private DirectoryEstablishmentScorer $directoryEstablishmentScorer,
    ) {
    }

    public function getAutoMatchScoreThreshold(): int
    {
        return self::MATCH_SCORE_THRESHOLDS['auto_match'];
    }

    public function findImportLinkIdByExternalId(string $source, DirectoryEstablishmentImportItem $item): ?int
    {
        return $this->directoryOrganizationLookup->findImportLinkIdByExternalId(
            source: $source,
            externalId: $item->externalId,
        );
    }

    public function findOrganizationIdLinkedByExternalIdentifiers(string $source, DirectoryEstablishmentImportItem $item): ?int
    {
        $exactOrganizationId = $this->directoryOrganizationLookup->findOrganizationIdByExternalId(
            source: $source,
            externalId: $item->externalId,
        );

        if (null !== $exactOrganizationId) {
            return $exactOrganizationId;
        }

        if (null !== $item->externalOrganizationId) {
            return $this->directoryOrganizationLookup->findOrganizationIdByExternalOrganizationId(
                source: $source,
                externalOrganizationId: $item->externalOrganizationId,
            );
        }

        return null;
    }

    /**
     * @return list<DirectoryEstablishmentMatch>
     */
    public function findOrganizationCandidates(DirectoryEstablishmentImportItem $item): array
    {
        $matches = [];

        foreach ($this->directoryOrganizationCandidateLookup->findOrganizationCandidates($item) as $organizationCandidate) {
            if (!$this->directoryEstablishmentScorer->isCommuneCompatible($item->commune, $organizationCandidate->commune)) {
                continue;
            }

            $score = $this->directoryEstablishmentScorer->computeCandidateScore($organizationCandidate, $item);

            if ($score < self::MATCH_SCORE_THRESHOLDS['candidate']) {
                continue;
            }

            $matches[] = new DirectoryEstablishmentMatch(
                organizationId: $organizationCandidate->organizationId,
                organizationName: $organizationCandidate->name,
                score: $score,
                email: $organizationCandidate->email,
                phone: $organizationCandidate->phone,
                commune: $organizationCandidate->commune,
                address: $organizationCandidate->address,
                website: $organizationCandidate->websiteUrl ?? '',
            );
        }

        usort($matches, static fn (DirectoryEstablishmentMatch $left, DirectoryEstablishmentMatch $right): int => $right->score <=> $left->score);

        return array_slice($matches, 0, 5);
    }
}
