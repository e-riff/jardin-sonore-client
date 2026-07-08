<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Domain\Model\ValueObject\PhoneNumber;
use InvalidArgumentException;

final readonly class DirectoryEstablishmentMatcher
{
    private const array MATCH_SCORE_THRESHOLDS = [
        'auto_match' => 90,
        'candidate' => 80,
    ];
    private const float COMMUNE_COMPATIBILITY_THRESHOLD = 92.0;
    private const array SCORE_BONUSES = [
        'email_exact' => 45,
        'phone_exact' => 25,
        'website_exact' => 15,
        'business_name_exact' => 20,
        'street_address_exact' => 20,
        'phone_street_address_strong_combo' => 25,
        'business_name_street_address_exact_combo' => 25,
        'strong_address_name_combo' => 25,
        'address_name_combo' => 15,
        'name_close_address_far_penalty' => 20,
    ];
    private const array SIMILARITY_WEIGHTS = [
        'name' => 0.2,
        'business_name' => 0.35,
        'commune' => 0.1,
        'address' => 0.35,
        'street_address' => 0.25,
    ];
    private const array SIMILARITY_THRESHOLDS = [
        'strong_address' => 85,
        'strong_street_address' => 90,
        'address' => 70,
        'street_address' => 75,
        'far_address' => 35,
        'far_street_address' => 40,
        'strong_name' => 60,
        'very_strong_name' => 70,
    ];
    /**
     * @var list<string>
     */
    private const array GENERIC_NAME_TOKENS = [
        'accueil',
        'centre',
        'collective',
        'creche',
        'eaje',
        'enfant',
        'enfants',
        'etablissement',
        'familiale',
        'familial',
        'garde',
        'halte',
        'jardin',
        'jeune',
        'maison',
        'micro',
        'microcreche',
        'micro-creche',
        'municipal',
        'municipale',
        'multi',
        'petite',
        'petit',
    ];

    public function __construct(
        private DirectoryOrganizationCandidateLookupInterface $directoryOrganizationCandidateLookup,
        private DirectoryOrganizationLookupInterface $directoryOrganizationLookup,
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
            if (!$this->isCommuneCompatible(
                $this->normalizeCommune($item->commune),
                $this->normalizeCommune($organizationCandidate->commune),
            )) {
                continue;
            }

            $score = $this->computeCandidateScore($organizationCandidate, $item);

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

    private function computeCandidateScore(DirectoryOrganizationCandidate $organizationCandidate, DirectoryEstablishmentImportItem $item): int
    {
        $score = 0;

        $importEmail = $this->normalizeEmail($item->emailAddress);
        $organizationEmail = $this->normalizeEmail($organizationCandidate->email);
        if (null !== $importEmail && null !== $organizationEmail && $importEmail === $organizationEmail) {
            $score += self::SCORE_BONUSES['email_exact'];
        }

        $importPhone = $this->normalizePhone($item->phoneNumber);
        $organizationPhone = $this->normalizePhone($organizationCandidate->phone);
        if (null !== $importPhone && null !== $organizationPhone && $importPhone === $organizationPhone) {
            $score += self::SCORE_BONUSES['phone_exact'];
        }

        $importWebsite = $this->normalizeWebsite($item->websiteUrl);
        $organizationWebsite = $this->normalizeWebsite($organizationCandidate->websiteUrl);
        if (null !== $importWebsite && null !== $organizationWebsite && $importWebsite === $organizationWebsite) {
            $score += self::SCORE_BONUSES['website_exact'];
        }

        $nameSimilarity = $this->similarityPercentage($organizationCandidate->name, $item->name);
        $score += (int) round($nameSimilarity * self::SIMILARITY_WEIGHTS['name']);

        $businessNameSimilarity = $this->businessNameSimilarityPercentage($organizationCandidate->name, $item->name);
        $score += (int) round($businessNameSimilarity * self::SIMILARITY_WEIGHTS['business_name']);

        $normalizedOrganizationBusinessName = $this->normalizeBusinessName($organizationCandidate->name);
        $normalizedImportBusinessName = $this->normalizeBusinessName($item->name);
        if ('' !== $normalizedOrganizationBusinessName && $normalizedOrganizationBusinessName === $normalizedImportBusinessName) {
            $score += self::SCORE_BONUSES['business_name_exact'];
        }

        $communeSimilarity = $this->similarityPercentage($organizationCandidate->commune, $item->commune);
        $score += (int) round($communeSimilarity * self::SIMILARITY_WEIGHTS['commune']);

        $addressSimilarity = $this->similarityPercentage($organizationCandidate->address, $item->address);
        $score += (int) round($addressSimilarity * self::SIMILARITY_WEIGHTS['address']);

        $streetAddressSimilarity = $this->streetAddressSimilarityPercentage($organizationCandidate->address, $item->address);
        $score += (int) round($streetAddressSimilarity * self::SIMILARITY_WEIGHTS['street_address']);

        $normalizedOrganizationStreetAddress = $this->normalizeStreetAddress($organizationCandidate->address);
        $normalizedImportStreetAddress = $this->normalizeStreetAddress($item->address);
        if ('' !== $normalizedOrganizationStreetAddress && $normalizedOrganizationStreetAddress === $normalizedImportStreetAddress) {
            $score += self::SCORE_BONUSES['street_address_exact'];
        }

        if (null !== $importPhone && null !== $organizationPhone && $importPhone === $organizationPhone && $streetAddressSimilarity >= self::SIMILARITY_THRESHOLDS['strong_address']) {
            $score += self::SCORE_BONUSES['phone_street_address_strong_combo'];
        }

        if (
            '' !== $normalizedOrganizationBusinessName
            && $normalizedOrganizationBusinessName === $normalizedImportBusinessName
            && '' !== $normalizedOrganizationStreetAddress
            && $normalizedOrganizationStreetAddress === $normalizedImportStreetAddress
        ) {
            $score += self::SCORE_BONUSES['business_name_street_address_exact_combo'];
        }

        if (($addressSimilarity >= self::SIMILARITY_THRESHOLDS['strong_address'] || $streetAddressSimilarity >= self::SIMILARITY_THRESHOLDS['strong_street_address']) && $businessNameSimilarity >= self::SIMILARITY_THRESHOLDS['strong_name']) {
            $score += self::SCORE_BONUSES['strong_address_name_combo'];
        } elseif (($addressSimilarity >= self::SIMILARITY_THRESHOLDS['address'] || $streetAddressSimilarity >= self::SIMILARITY_THRESHOLDS['street_address']) && $nameSimilarity >= self::SIMILARITY_THRESHOLDS['strong_name']) {
            $score += self::SCORE_BONUSES['address_name_combo'];
        } elseif ($addressSimilarity <= self::SIMILARITY_THRESHOLDS['far_address'] && $streetAddressSimilarity <= self::SIMILARITY_THRESHOLDS['far_street_address'] && ($nameSimilarity >= self::SIMILARITY_THRESHOLDS['very_strong_name'] || $businessNameSimilarity >= self::SIMILARITY_THRESHOLDS['very_strong_name'])) {
            $score -= self::SCORE_BONUSES['name_close_address_far_penalty'];
        }

        return min(100, $score);
    }

    private function normalizeEmail(?string $emailAddress): ?string
    {
        if (null === $emailAddress) {
            return null;
        }

        $emailAddress = mb_strtolower(trim($emailAddress));

        return '' === $emailAddress ? null : $emailAddress;
    }

    private function normalizePhone(?string $phoneNumber): ?string
    {
        if (null === $phoneNumber) {
            return null;
        }

        $phoneNumber = trim($phoneNumber);

        if ('' === $phoneNumber) {
            return null;
        }

        try {
            return PhoneNumber::normalize($phoneNumber);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function normalizeWebsite(?string $websiteUrl): ?string
    {
        if (null === $websiteUrl) {
            return null;
        }

        $websiteUrl = trim($websiteUrl);

        if ('' === $websiteUrl) {
            return null;
        }

        $host = parse_url($websiteUrl, PHP_URL_HOST);
        $host = is_string($host) ? mb_strtolower($host) : mb_strtolower($websiteUrl);
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return '' === $host ? null : $host;
    }

    private function normalizeText(?string $value): string
    {
        $value = null === $value ? '' : trim($value);

        if ('' === $value) {
            return '';
        }

        $asciiValue = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $asciiValue = false === $asciiValue ? $value : $asciiValue;
        $asciiValue = mb_strtolower($asciiValue);
        $asciiValue = preg_replace('/[^a-z0-9]+/', ' ', $asciiValue) ?? $asciiValue;

        return trim(preg_replace('/\s+/', ' ', $asciiValue) ?? $asciiValue);
    }

    private function normalizeBusinessName(?string $value): string
    {
        $normalized = $this->normalizeText($value);

        if ('' === $normalized) {
            return '';
        }

        $relevantTerms = $this->extractRelevantNameTerms($value);

        if ([] === $relevantTerms) {
            return $normalized;
        }

        return implode(' ', $relevantTerms);
    }

    /**
     * @return list<string>
     */
    private function extractRelevantNameTerms(?string $value): array
    {
        $normalized = $this->normalizeText($value);

        if ('' === $normalized) {
            return [];
        }

        $terms = array_values(array_filter(
            explode(' ', $normalized),
            fn (string $term): bool => 2 <= mb_strlen($term) && !in_array($term, self::GENERIC_NAME_TOKENS, true),
        ));

        return [] === $terms
            ? array_values(array_filter(explode(' ', $normalized), static fn (string $term): bool => 2 <= mb_strlen($term)))
            : $terms;
    }

    private function similarityPercentage(?string $left, ?string $right): int
    {
        $left = $this->normalizeText($left);
        $right = $this->normalizeText($right);

        if ('' === $left || '' === $right) {
            return 0;
        }

        similar_text($left, $right, $percentage);

        return (int) round($percentage);
    }

    private function businessNameSimilarityPercentage(?string $left, ?string $right): int
    {
        $left = $this->normalizeBusinessName($left);
        $right = $this->normalizeBusinessName($right);

        if ('' === $left || '' === $right) {
            return 0;
        }

        similar_text($left, $right, $percentage);

        return (int) round($percentage);
    }

    private function streetAddressSimilarityPercentage(?string $left, ?string $right): int
    {
        $left = $this->normalizeStreetAddress($left);
        $right = $this->normalizeStreetAddress($right);

        if ('' === $left || '' === $right) {
            return 0;
        }

        similar_text($left, $right, $percentage);

        return (int) round($percentage);
    }

    private function normalizeStreetAddress(?string $value): string
    {
        $normalized = $this->normalizeText($value);

        if ('' === $normalized) {
            return '';
        }

        $normalized = preg_replace('/\b\d{5}\b/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b(nr|numero|num|n)\b/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bsaint\b/', 'st', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bsainte\b/', 'ste', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    private function normalizeCommune(?string $value): string
    {
        $normalized = $this->normalizeText($value);

        if ('' === $normalized) {
            return '';
        }

        $normalized = preg_replace('/\bsaint\b/', 'st', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bsainte\b/', 'ste', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function isCommuneCompatible(string $normalizedImportCommune, string $normalizedCandidateCommune): bool
    {
        if ('' === $normalizedImportCommune || '' === $normalizedCandidateCommune) {
            return true;
        }

        if ($normalizedImportCommune === $normalizedCandidateCommune) {
            return true;
        }

        similar_text($normalizedImportCommune, $normalizedCandidateCommune, $percentage);

        return self::COMMUNE_COMPATIBILITY_THRESHOLD <= $percentage;
    }
}
