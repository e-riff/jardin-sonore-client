<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Infrastructure\Doctrine\Entity\DirectoryImportLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DirectoryEstablishmentMatcher
{
    private const array MATCH_SCORE_THRESHOLDS = [
        'auto_match' => 90,
        'candidate' => 70,
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
    private const CANDIDATE_QUERY_LIMIT = 200;

    /**
     * @var list<string>
     */
    private const GENERIC_NAME_TOKENS = [
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
        'municipal',
        'municipale',
        'multi',
        'petite',
        'petit',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getAutoMatchScoreThreshold(): int
    {
        return self::MATCH_SCORE_THRESHOLDS['auto_match'];
    }

    public function findImportLinkByExternalId(string $source, DirectoryEstablishmentImportItem $item): ?DirectoryImportLinkEntity
    {
        $repository = $this->entityManager->getRepository(DirectoryImportLinkEntity::class);

        $importLink = $repository->findOneBy([
            'source' => $source,
            'externalId' => $item->externalId,
        ]);

        return $importLink instanceof DirectoryImportLinkEntity ? $importLink : null;
    }

    public function findOrganizationLinkedByExternalIdentifiers(string $source, DirectoryEstablishmentImportItem $item): ?OrganizationEntity
    {
        $exactImportLink = $this->findImportLinkByExternalId($source, $item);

        if ($exactImportLink instanceof DirectoryImportLinkEntity) {
            $directoryEntry = $exactImportLink->getDirectoryEntry();

            return $directoryEntry instanceof OrganizationEntity ? $directoryEntry : null;
        }

        if (null !== $item->externalOrganizationId) {
            $importLink = $this->entityManager->getRepository(DirectoryImportLinkEntity::class)->findOneBy([
                'source' => $source,
                'externalOrganizationId' => $item->externalOrganizationId,
            ]);

            if ($importLink instanceof DirectoryImportLinkEntity) {
                $directoryEntry = $importLink->getDirectoryEntry();

                return $directoryEntry instanceof OrganizationEntity ? $directoryEntry : null;
            }
        }

        return null;
    }

    /**
     * @return list<DirectoryEstablishmentMatch>
     */
    public function findOrganizationCandidates(DirectoryEstablishmentImportItem $item): array
    {
        $matches = [];

        foreach ($this->findRawOrganizationCandidates($item) as $rawCandidate) {
            $score = $this->computeCandidateScore($rawCandidate, $item);

            if ($score < self::MATCH_SCORE_THRESHOLDS['candidate']) {
                continue;
            }

            $organizationCandidate = $this->entityManager->find(OrganizationEntity::class, $rawCandidate['id']);

            if (!$organizationCandidate instanceof OrganizationEntity) {
                continue;
            }

            $matches[] = new DirectoryEstablishmentMatch(
                organization: $organizationCandidate,
                score: $score,
                email: $rawCandidate['email'],
                phone: $rawCandidate['phone'],
                commune: $rawCandidate['commune'],
                address: $rawCandidate['address'],
                website: $rawCandidate['website_url'] ?? '',
            );
        }

        usort($matches, static fn (DirectoryEstablishmentMatch $left, DirectoryEstablishmentMatch $right): int => $right->score <=> $left->score);

        return array_slice($matches, 0, 5);
    }

    /**
     * @param array{id:int, name:string, website_url:?string, email:string, phone:string, commune:string, address:string} $organization
     */
    private function computeCandidateScore(array $organization, DirectoryEstablishmentImportItem $item): int
    {
        $score = 0;

        $importEmail = $this->normalizeEmail($item->emailAddress);
        $organizationEmail = $this->normalizeEmail($organization['email']);
        if (null !== $importEmail && null !== $organizationEmail && $importEmail === $organizationEmail) {
            $score += self::SCORE_BONUSES['email_exact'];
        }

        $importPhone = $this->normalizePhone($item->phoneNumber);
        $organizationPhone = $this->normalizePhone($organization['phone']);
        if (null !== $importPhone && null !== $organizationPhone && $importPhone === $organizationPhone) {
            $score += self::SCORE_BONUSES['phone_exact'];
        }

        $importWebsite = $this->normalizeWebsite($item->websiteUrl);
        $organizationWebsite = $this->normalizeWebsite($organization['website_url']);
        if (null !== $importWebsite && null !== $organizationWebsite && $importWebsite === $organizationWebsite) {
            $score += self::SCORE_BONUSES['website_exact'];
        }

        $nameSimilarity = $this->similarityPercentage($organization['name'], $item->name);
        $score += (int) round($nameSimilarity * self::SIMILARITY_WEIGHTS['name']);

        $businessNameSimilarity = $this->businessNameSimilarityPercentage($organization['name'], $item->name);
        $score += (int) round($businessNameSimilarity * self::SIMILARITY_WEIGHTS['business_name']);

        $normalizedOrganizationBusinessName = $this->normalizeBusinessName($organization['name']);
        $normalizedImportBusinessName = $this->normalizeBusinessName($item->name);
        if ('' !== $normalizedOrganizationBusinessName && $normalizedOrganizationBusinessName === $normalizedImportBusinessName) {
            $score += self::SCORE_BONUSES['business_name_exact'];
        }

        $communeSimilarity = $this->similarityPercentage($organization['commune'], $item->commune);
        $score += (int) round($communeSimilarity * self::SIMILARITY_WEIGHTS['commune']);

        $addressSimilarity = $this->similarityPercentage($organization['address'], $item->address);
        $score += (int) round($addressSimilarity * self::SIMILARITY_WEIGHTS['address']);

        $streetAddressSimilarity = $this->streetAddressSimilarityPercentage($organization['address'], $item->address);
        $score += (int) round($streetAddressSimilarity * self::SIMILARITY_WEIGHTS['street_address']);

        $normalizedOrganizationStreetAddress = $this->normalizeStreetAddress($organization['address']);
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

    /**
     * @return list<array{id:int, name:string, website_url:?string, email:string, phone:string, commune:string, address:string}>
     */
    private function findRawOrganizationCandidates(DirectoryEstablishmentImportItem $item): array
    {
        $queryBuilder = $this->entityManager->getConnection()->createQueryBuilder()
            ->select(
                'organization.id',
                'organization.name',
                'organization.website_url',
                'COALESCE(MIN(email.email_address), \'\') AS email',
                'COALESCE(MIN(phone.phone_number), \'\') AS phone',
                'COALESCE(MIN(address.city), \'\') AS commune',
                'COALESCE(MIN(address.address), \'\') AS address',
            )
            ->from('organization', 'organization')
            ->leftJoin('organization', 'contact_details', 'contact', 'contact.directory_entry_id = organization.id')
            ->leftJoin('contact', 'contact_details_email_link', 'email_link', 'email_link.contact_details_id = contact.id')
            ->leftJoin('email_link', 'email_contact', 'email', 'email.id = email_link.email_contact_id')
            ->leftJoin('contact', 'contact_details_phone_link', 'phone_link', 'phone_link.contact_details_id = contact.id')
            ->leftJoin('phone_link', 'phone_contact', 'phone', 'phone.id = phone_link.phone_contact_id')
            ->leftJoin('contact', 'address_contact', 'address', 'address.contact_details_id = contact.id')
            ->groupBy('organization.id', 'organization.name', 'organization.website_url')
            ->setMaxResults(self::CANDIDATE_QUERY_LIMIT);

        $orWhere = [];

        $nameTerms = $this->extractRelevantNameTerms($item->name);
        if ([] !== $nameTerms) {
            $quotedName = $this->extractQuotedName($item->name);

            if (null !== $quotedName) {
                $orWhere[] = 'LOWER(organization.name) LIKE :quotedName';
                $queryBuilder->setParameter('quotedName', '%' . $quotedName . '%');
            }

            foreach (array_slice($nameTerms, 0, 3) as $index => $nameTerm) {
                $parameterName = "nameTerm{$index}";
                $orWhere[] = "LOWER(organization.name) LIKE :{$parameterName}";
                $queryBuilder->setParameter($parameterName, '%' . $nameTerm . '%');
            }
        }

        $emailAddress = $this->normalizeEmail($item->emailAddress);
        if (null !== $emailAddress) {
            $orWhere[] = 'LOWER(email.email_address) = :emailAddress';
            $queryBuilder->setParameter('emailAddress', $emailAddress);
        }

        $phoneNumber = $this->normalizePhone($item->phoneNumber);
        if (null !== $phoneNumber) {
            $orWhere[] = 'phone.phone_number = :phoneNumber';
            $queryBuilder->setParameter('phoneNumber', $phoneNumber);
        }

        if (null !== $item->commune) {
            $orWhere[] = 'LOWER(address.city) = :commune';
            $queryBuilder->setParameter('commune', mb_strtolower($item->commune));
        }

        $websiteUrl = $this->normalizeWebsite($item->websiteUrl);
        if (null !== $websiteUrl) {
            $orWhere[] = 'LOWER(organization.website_url) LIKE :websiteUrl';
            $queryBuilder->setParameter('websiteUrl', '%' . $websiteUrl . '%');
        }

        if ([] === $orWhere) {
            return [];
        }

        $queryBuilder->andWhere('(' . implode(' OR ', $orWhere) . ')');

        /** @var list<array{id:int|string, name:string, website_url:?string, email:string, phone:string, commune:string, address:string}> $rows */
        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        $normalizedImportCommune = $this->normalizeCommune($item->commune);
        $rows = array_filter($rows, fn (array $row): bool => $this->isCommuneCompatible(
            $normalizedImportCommune,
            $this->normalizeCommune((string) $row['commune']),
        ));

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'website_url' => is_string($row['website_url']) ? $row['website_url'] : null,
            'email' => (string) $row['email'],
            'phone' => (string) $row['phone'],
            'commune' => (string) $row['commune'],
            'address' => (string) $row['address'],
        ], $rows);
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

        $phoneNumber = preg_replace('/[^\d+]/', '', $phoneNumber) ?? '';

        return '' === $phoneNumber ? null : $phoneNumber;
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

    private function extractQuotedName(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (1 !== preg_match('/["“](.+?)["”]/u', $value, $matches)) {
            return null;
        }

        $quotedName = $this->normalizeText($matches[1]);

        return '' === $quotedName ? null : $quotedName;
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
