<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectoryDuplicateCandidate;
use App\Application\Directory\DirectoryDuplicateCandidateLookupInterface;
use App\Domain\Model\ValueObject\PhoneNumber;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

final readonly class DoctrineDirectoryDuplicateCandidateLookup implements DirectoryDuplicateCandidateLookupInterface
{
    private const int MAXIMUM_CANDIDATE_LIMIT = 500;

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findCandidates(int $limit): array
    {
        $limit = min(self::MAXIMUM_CANDIDATE_LIMIT, max(1, $limit));
        $organizationsById = $this->findOrganizations();
        $candidateSignals = [];
        $this->addGroupedSignals($candidateSignals, $organizationsById, $this->findActiveAddressRows(), 'same_address', $limit);
        $this->addGroupedSignals($candidateSignals, $organizationsById, $this->findEmailRows(), 'same_email', $limit);
        $this->addGroupedSignals($candidateSignals, $organizationsById, $this->findPhoneRows(), 'same_phone', $limit);
        $this->addNameSignals($candidateSignals, $organizationsById, $limit);

        uasort($candidateSignals, static function (array $left, array $right): int {
            $leftWeight = self::signalWeight($left['signals']);
            $rightWeight = self::signalWeight($right['signals']);

            if ($leftWeight !== $rightWeight) {
                return $rightWeight <=> $leftWeight;
            }

            return [$left['first_id'], $left['second_id']] <=> [$right['first_id'], $right['second_id']];
        });

        $candidates = [];

        foreach (array_slice($candidateSignals, 0, $limit) as $candidateSignal) {
            $candidates[] = new DirectoryDuplicateCandidate(
                firstOrganizationId: $candidateSignal['first_id'],
                firstOrganizationName: $candidateSignal['first_name'],
                firstLocation: $candidateSignal['first_location'],
                secondOrganizationId: $candidateSignal['second_id'],
                secondOrganizationName: $candidateSignal['second_name'],
                secondLocation: $candidateSignal['second_location'],
                signals: array_keys($candidateSignal['signals']),
            );
        }

        return $candidates;
    }

    /**
     * @return array<int, array{id:int, name:string, location:string, locations:array<string, true>}>
     */
    private function findOrganizations(): array
    {
        $organizations = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, name FROM organization ORDER BY id') as $row) {
            $organizations[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'location' => '—',
                'locations' => [],
            ];
        }

        foreach ($this->connection->fetchAllAssociative(
            'SELECT contact.directory_entry_id, address.address, address.postal_code, address.city, address.active
            FROM contact_details contact
            JOIN address_contact address ON address.contact_details_id = contact.id
            ORDER BY contact.directory_entry_id, address.active DESC, address.id',
        ) as $row) {
            $organizationId = (int) $row['directory_entry_id'];
            if (!isset($organizations[$organizationId])) {
                continue;
            }

            $locationParts = array_filter([
                trim((string) ($row['address'] ?? '')),
                trim((string) ($row['postal_code'] ?? '')),
                trim((string) ($row['city'] ?? '')),
            ]);
            if ([] === $locationParts) {
                continue;
            }

            $location = implode(' ', $locationParts);
            if (0 === (int) ($row['active'] ?? 0)) {
                $location .= ' (inactive)';
            }
            $organizations[$organizationId]['locations'][$location] = true;
        }

        foreach ($organizations as &$organization) {
            $organization['location'] = [] === $organization['locations'] ? '—' : implode(' ; ', array_keys($organization['locations']));
        }
        unset($organization);

        return $organizations;
    }

    /**
     * @param array<string, array{first_id:int, first_name:string, first_location:string, second_id:int, second_name:string, second_location:string, signals:array<string, true>}> $candidateSignals
     * @param array<int, array{id:int, name:string, location:string, locations:array<string, true>}>                                                                               $organizations
     * @param list<array{directory_entry_id:int|string, value:string}>                                                                                                             $rows
     */
    private function addGroupedSignals(array &$candidateSignals, array $organizations, array $rows, string $signal, int $limit): void
    {
        $groups = [];
        foreach ($rows as $row) {
            $organizationId = (int) $row['directory_entry_id'];
            if (!isset($organizations[$organizationId])) {
                continue;
            }

            $value = $this->normalizeSignal($row['value'], $signal);
            if ('' !== $value) {
                $groups[$value][$organizationId] = true;
            }
        }
        ksort($groups);

        $pairCount = 0;
        foreach ($groups as $organizationIds) {
            $organizationIds = array_keys($organizationIds);
            if (2 > count($organizationIds) || 20 < count($organizationIds)) {
                continue;
            }
            sort($organizationIds);

            for ($firstIndex = 0, $organizationCount = count($organizationIds); $firstIndex < $organizationCount - 1; ++$firstIndex) {
                for ($secondIndex = $firstIndex + 1; $secondIndex < $organizationCount; ++$secondIndex) {
                    $this->addPairSignal($candidateSignals, $organizations, $organizationIds[$firstIndex], $organizationIds[$secondIndex], $signal);
                    ++$pairCount;
                    if ($pairCount >= 5 * $limit) {
                        return;
                    }
                }
            }
        }
    }

    /**
     * @param array<string, array{first_id:int, first_name:string, first_location:string, second_id:int, second_name:string, second_location:string, signals:array<string, true>}> $candidateSignals
     * @param array<int, array{id:int, name:string, location:string, locations:array<string, true>}>                                                                               $organizations
     */
    private function addNameSignals(array &$candidateSignals, array $organizations, int $limit): void
    {
        $groups = [];
        foreach ($organizations as $organizationId => $organization) {
            $name = $this->normalizeSignal($organization['name'], 'same_name');
            if ('' !== $name) {
                $groups[$name][] = $organizationId;
            }
        }
        ksort($groups);

        $pairCount = 0;
        foreach ($groups as $organizationIds) {
            if (2 > count($organizationIds) || 20 < count($organizationIds)) {
                continue;
            }

            for ($firstIndex = 0, $organizationCount = count($organizationIds); $firstIndex < $organizationCount - 1; ++$firstIndex) {
                for ($secondIndex = $firstIndex + 1; $secondIndex < $organizationCount; ++$secondIndex) {
                    $this->addPairSignal($candidateSignals, $organizations, $organizationIds[$firstIndex], $organizationIds[$secondIndex], 'same_name');
                    ++$pairCount;
                    if ($pairCount >= 5 * $limit) {
                        return;
                    }
                }
            }
        }
    }

    /**
     * @param array<string, array{first_id:int, first_name:string, first_location:string, second_id:int, second_name:string, second_location:string, signals:array<string, true>}> $candidateSignals
     * @param array<int, array{id:int, name:string, location:string, locations:array<string, true>}>                                                                               $organizations
     */
    private function addPairSignal(array &$candidateSignals, array $organizations, int $firstId, int $secondId, string $signal): void
    {
        $key = $firstId . ':' . $secondId;
        $candidateSignals[$key] ??= [
            'first_id' => $firstId,
            'first_name' => $organizations[$firstId]['name'],
            'first_location' => $organizations[$firstId]['location'],
            'second_id' => $secondId,
            'second_name' => $organizations[$secondId]['name'],
            'second_location' => $organizations[$secondId]['location'],
            'signals' => [],
        ];
        $candidateSignals[$key]['signals'][$signal] = true;
    }

    /**
     * @return list<array{directory_entry_id:int|string, value:string}>
     */
    private function findActiveAddressRows(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT contact.directory_entry_id,
                CONCAT(COALESCE(address.address, \'\'), \'|\', COALESCE(address.postal_code, \'\'), \'|\', COALESCE(address.city, \'\')) AS value
            FROM contact_details contact
            JOIN address_contact address ON address.contact_details_id = contact.id
            WHERE address.active = 1 AND TRIM(COALESCE(address.address, \'\')) <> \'\'',
        );

        return array_map(static fn (array $row): array => ['directory_entry_id' => $row['directory_entry_id'], 'value' => (string) $row['value']], $rows);
    }

    /**
     * @return list<array{directory_entry_id:int|string, value:string}>
     */
    private function findEmailRows(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT contact.directory_entry_id, LOWER(TRIM(email.email_address)) AS value
            FROM contact_details contact
            JOIN contact_details_email_link email_link ON email_link.contact_details_id = contact.id
            JOIN email_contact email ON email.id = email_link.email_contact_id
            WHERE TRIM(email.email_address) <> \'\'',
        );

        return array_map(static fn (array $row): array => ['directory_entry_id' => $row['directory_entry_id'], 'value' => (string) $row['value']], $rows);
    }

    /**
     * @return list<array{directory_entry_id:int|string, value:string}>
     */
    private function findPhoneRows(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT contact.directory_entry_id, phone.phone_number AS value
            FROM contact_details contact
            JOIN contact_details_phone_link phone_link ON phone_link.contact_details_id = contact.id
            JOIN phone_contact phone ON phone.id = phone_link.phone_contact_id
            WHERE TRIM(phone.phone_number) <> \'\'',
        );

        return array_map(static fn (array $row): array => ['directory_entry_id' => $row['directory_entry_id'], 'value' => (string) $row['value']], $rows);
    }

    /**
     * @param 'same_name'|'same_address'|'same_email'|'same_phone' $signal
     */
    private function normalizeSignal(string $value, string $signal): string
    {
        if ('same_email' === $signal) {
            return mb_strtolower(trim($value));
        }

        if ('same_phone' === $signal) {
            try {
                return PhoneNumber::normalize($value);
            } catch (InvalidArgumentException) {
                return trim($value);
            }
        }

        $asciiValue = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value));
        $normalizedValue = mb_strtolower(false === $asciiValue ? $value : $asciiValue);
        $normalizedValue = preg_replace('/[^a-z0-9]+/', ' ', $normalizedValue) ?? $normalizedValue;

        return trim(preg_replace('/\s+/', ' ', $normalizedValue) ?? $normalizedValue);
    }

    /**
     * @param array<string, true> $signals
     */
    private static function signalWeight(array $signals): int
    {
        return (4 * (int) isset($signals['same_address']))
            + (3 * (int) isset($signals['same_email']))
            + (3 * (int) isset($signals['same_phone']))
            + (2 * (int) isset($signals['same_name']));
    }
}
