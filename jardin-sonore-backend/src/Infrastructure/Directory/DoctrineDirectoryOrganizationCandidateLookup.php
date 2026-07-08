<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Application\Directory\DirectoryOrganizationCandidate;
use App\Application\Directory\DirectoryOrganizationCandidateLookupInterface;
use App\Domain\Model\ValueObject\PhoneNumber;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final readonly class DoctrineDirectoryOrganizationCandidateLookup implements DirectoryOrganizationCandidateLookupInterface
{
    private const int CANDIDATE_QUERY_LIMIT = 200;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findOrganizationCandidates(DirectoryEstablishmentImportItem $item): array
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

        $this->applyLookupFilters($queryBuilder, $item);

        /** @var list<array{id:int|string, name:string, website_url:?string, email:string, phone:string, commune:string, address:string}> $rows */
        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        return array_map(static fn (array $row): DirectoryOrganizationCandidate => new DirectoryOrganizationCandidate(
            organizationId: (int) $row['id'],
            name: (string) $row['name'],
            websiteUrl: is_string($row['website_url']) ? $row['website_url'] : null,
            email: (string) $row['email'],
            phone: (string) $row['phone'],
            commune: (string) $row['commune'],
            address: (string) $row['address'],
        ), $rows);
    }

    private function applyLookupFilters(QueryBuilder $queryBuilder, DirectoryEstablishmentImportItem $item): void
    {
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

        $commune = $this->normalizeText($item->commune);
        if ('' !== $commune) {
            $orWhere[] = 'LOWER(address.city) = :commune';
            $queryBuilder->setParameter('commune', $commune);
        }

        $websiteUrl = $this->normalizeWebsite($item->websiteUrl);
        if (null !== $websiteUrl) {
            $orWhere[] = 'LOWER(organization.website_url) LIKE :websiteUrl';
            $queryBuilder->setParameter('websiteUrl', '%' . $websiteUrl . '%');
        }

        if ([] === $orWhere) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $queryBuilder->andWhere('(' . implode(' OR ', $orWhere) . ')');
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

    /**
     * @return list<string>
     */
    private function extractRelevantNameTerms(?string $value): array
    {
        $normalized = $this->normalizeText($value);

        if ('' === $normalized) {
            return [];
        }

        $genericTokens = [
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

        $terms = array_values(array_filter(
            explode(' ', $normalized),
            static fn (string $term): bool => 2 <= mb_strlen($term) && !in_array($term, $genericTokens, true),
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
}
