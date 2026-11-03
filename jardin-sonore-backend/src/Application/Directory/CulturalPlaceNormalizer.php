<?php

declare(strict_types=1);

namespace App\Application\Directory;

final class CulturalPlaceNormalizer
{
    /**
     * @param array<string, mixed> $row
     *
     * @return array{item: array<string, mixed>, youth: bool, sourceUpdatedAt: ?string}|null
     */
    public function normalize(array $row, string $department): ?array
    {
        if (($row['code_departement'] ?? null) !== strtoupper($department)) {
            return null;
        }
        $domains = $this->values($row['sous_domaines'] ?? null);
        $types = $this->values($row['types'] ?? null);
        $youth = in_array('Jeunesse', $types, true);
        $type = match (true) {
            in_array('Bibliothèque et médiathèque', $domains, true) => 'mediatheque',
            in_array('Librairie', $domains, true) => $youth ? 'librairie_jeunesse' : 'librairie',
            in_array('Théâtre', $domains, true) => 'salle_spectacle',
            in_array('Musique', $domains, true) => 'salle_spectacle',
            in_array('Centre culturel', $domains, true) => 'centre culturel',
            default => null,
        };
        $id = $row['id'] ?? null;
        $name = $row['nom_usuel_du_lieu'] ?? $row['nom_du_lieu'] ?? null;
        if (null === $type || !is_string($id) || '' === trim($id) || !is_string($name) || '' === trim($name)) {
            return null;
        }
        $emails = $this->values($row['courriels'] ?? null);
        $phones = $this->values($row['telephones'] ?? null);
        $websites = $this->values($row['site_internet_et_autres_liens'] ?? null);

        return [
            'item' => [
                'id' => $id, 'type' => $type, 'name' => $name,
                'mail' => $emails[0] ?? null, 'additionalEmails' => array_slice($emails, 1),
                'phone' => $phones[0] ?? null, 'additionalPhones' => array_slice($phones, 1),
                'siteWeb' => $websites[0] ?? null,
                'address' => $row['adresse_entree_public'] ?? $row['adresse_complete'] ?? null,
                'commune' => $row['commune'] ?? null,
                'codeCommune' => $row['code_commune'] ?? null,
            ],
            'youth' => $youth,
            'sourceUpdatedAt' => is_string($row['date_modification'] ?? null) ? $row['date_modification'] : null,
        ];
    }

    /** @return list<string> */
    private function values(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded) && str_starts_with(trim($value), '[')) {
                $decoded = json_decode("[{$value}]", true);
            }
            $value = is_array($decoded) ? $decoded : [$value];
        }
        if (!is_array($value)) {
            return [];
        }

        $values = [];
        foreach ($value as $item) {
            if (is_string($item) && '' !== trim($item)) {
                $values[] = $item;
            } elseif (is_array($item)) {
                foreach ($item as $nested) {
                    if (is_string($nested) && '' !== trim($nested)) {
                        $values[] = $nested;
                    }
                }
            }
        }

        return $values;
    }
}
