<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Application\Directory\DirectoryEstablishmentMatch;
use App\Application\Directory\DirectoryEstablishmentMatcher;
use App\Domain\Model\AddressBook\OrganizationType;
use Doctrine\DBAL\Connection;

final readonly class CulturalPlaceCandidateFinder
{
    public function __construct(private Connection $connection, private DirectoryEstablishmentMatcher $matcher)
    {
    }

    /** @return list<DirectoryEstablishmentMatch> */
    public function findCandidates(DirectoryEstablishmentImportItem $item): array
    {
        $matches = $this->matcher->findOrganizationCandidates($item);
        $seen = [];
        foreach ($matches as $match) {
            $seen[$match->organizationId] = true;
        }
        $name = $this->normalizeName($item->name);
        $code = $item->municipalityInseeCode;
        if ('' === $name || null === $code) {
            return $matches;
        }
        $terms = explode(' ', $name);
        usort($terms, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $term = $terms[0] ?? '';
        $rows = $this->connection->fetchAllAssociative(
            "SELECT o.id, o.name, o.type, o.website_url, COALESCE(MIN(e.email_address), '') AS email,
                    COALESCE(MIN(p.phone_number), '') AS phone, COALESCE(MIN(m.name), '') AS commune,
                    COALESCE(MIN(a.address), '') AS address,
                    MAX(CASE WHEN m.insee_code = :code THEN 1 ELSE 0 END) AS same_commune
             FROM organization o
             LEFT JOIN contact_details c ON c.directory_entry_id = o.id
             LEFT JOIN contact_details_email_link el ON el.contact_details_id = c.id
             LEFT JOIN email_contact e ON e.id = el.email_contact_id
             LEFT JOIN contact_details_phone_link pl ON pl.contact_details_id = c.id
             LEFT JOIN phone_contact p ON p.id = pl.phone_contact_id
             LEFT JOIN address_contact a ON a.contact_details_id = c.id
             LEFT JOIN municipality m ON m.id = a.municipality_id
             WHERE m.insee_code = :code2 OR LOWER(o.name) LIKE :term
             GROUP BY o.id, o.name, o.type, o.website_url",
            ['code' => $code, 'code2' => $code, 'term' => 5 <= strlen($term) ? "%{$term}%" : ''],
        );
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $candidateName = $this->normalizeName((string) $row['name']);
            if ('' === $candidateName) {
                continue;
            }
            similar_text($name, $candidateName, $percent);
            $sameCommune = 1 === (int) $row['same_commune'];
            $strongName = 8 <= strlen($name) && (str_ends_with($candidateName, $name) || str_ends_with($name, $candidateName));
            if ((!$sameCommune || 70 > $percent) && !$strongName) {
                continue;
            }
            $matches[] = new DirectoryEstablishmentMatch(
                organizationId: $id,
                organizationName: (string) $row['name'],
                score: (int) round($percent),
                email: (string) $row['email'],
                phone: (string) $row['phone'],
                commune: (string) $row['commune'],
                address: (string) $row['address'],
                website: (string) ($row['website_url'] ?? ''),
                type: is_string($row['type']) ? OrganizationType::tryFrom($row['type']) : null,
            );
        }
        usort($matches, static fn (DirectoryEstablishmentMatch $a, DirectoryEstablishmentMatch $b): int => ($b->score <=> $a->score) ?: ($a->organizationId <=> $b->organizationId));

        return array_slice($matches, 0, 10);
    }

    private function normalizeName(?string $value): string
    {
        $value = mb_strtolower($value ?? '');
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: '';
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        $value = preg_replace('/\bsaint\b/', 'st', $value) ?? $value;
        $value = preg_replace('/\bsainte\b/', 'ste', $value) ?? $value;
        $value = preg_replace('/\b(bibliotheque|mediatheque|municipale|municipal|de|des|du|la|le|les)\b/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
