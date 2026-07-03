<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:address:link-municipalities',
    description: 'Link address contacts to municipalities using postal code and city matching.',
)]
final readonly class LinkAddressMunicipalitiesCommand
{
    private const int READ_BATCH_SIZE = 200;
    private const int FLUSH_BATCH_SIZE = 100;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Persist changes instead of running a dry-run.')]
        bool $apply = false,
        #[Option(description: 'Export unmatched addresses to a CSV file in var/export/.')]
        bool $file = false,
    ): int {
        $stats = [
            'processed' => 0,
            'linked' => 0,
            'exactMatched' => 0,
            'uniquePostalCodeMatched' => 0,
            'bestCityMatched' => 0,
            'departmentFallbackMatched' => 0,
            'missingPostalCode' => 0,
            'missingCity' => 0,
            'noCandidateForPostalCode' => 0,
            'notFound' => 0,
            'errors' => 0,
        ];
        $postalCodeCandidatesCache = [];
        $departmentCandidatesCache = [];
        $unmatchedAddresses = [];
        $pendingChangesSinceLastFlush = false;
        $lastFlushedProcessed = 0;

        foreach ($this->iterateAddressSnapshots() as $addressSnapshot) {
            ++$stats['processed'];

            $postalCode = $this->resolvePostalCode($addressSnapshot);
            if (null === $postalCode) {
                ++$stats['missingPostalCode'];
                $this->recordUnmatchedAddress(
                    $unmatchedAddresses,
                    $addressSnapshot,
                    'missing_postal_code',
                    'Code postal absent ou introuvable dans l’adresse.',
                );
                $this->flushCheckpointIfNeeded($io, $apply, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);

                continue;
            }

            $normalizedCity = $this->normalizeCommune($addressSnapshot['city']);
            if ('' === $normalizedCity) {
                ++$stats['missingCity'];
                $this->recordUnmatchedAddress(
                    $unmatchedAddresses,
                    $addressSnapshot,
                    'missing_city',
                    'Ville absente ou inexploitable pour le matching.',
                );
                $this->flushCheckpointIfNeeded($io, $apply, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);

                continue;
            }

            $postalCodeCandidatesCache[$postalCode] ??= $this->loadMunicipalityCandidatesByPostalCode($postalCode);
            $candidates = $postalCodeCandidatesCache[$postalCode];

            if ([] === $candidates) {
                $departmentCode = $this->inferDepartmentCodeFromPostalCode($postalCode);

                if (null !== $departmentCode) {
                    $departmentCandidatesCache[$departmentCode] ??= $this->loadMunicipalityCandidatesByDepartmentCode($departmentCode);
                    $resolution = $this->resolveMunicipalityCandidateWithinDepartment($normalizedCity, $departmentCandidatesCache[$departmentCode]);

                    if (null !== $resolution) {
                        ++$stats['linked'];
                        ++$stats['departmentFallbackMatched'];

                        if ($apply) {
                            try {
                                $addressContact = $this->entityManager->find(AddressContactEntity::class, $addressSnapshot['id']);
                                $municipality = $this->entityManager->find(MunicipalityEntity::class, $resolution['municipalityId']);

                                if ($addressContact instanceof AddressContactEntity && $municipality instanceof MunicipalityEntity) {
                                    $addressContact->setMunicipality($municipality);
                                    $this->entityManager->persist($addressContact);
                                    $pendingChangesSinceLastFlush = true;
                                } else {
                                    ++$stats['errors'];
                                }
                            } catch (Throwable) {
                                ++$stats['errors'];
                            }
                        }

                        $this->flushCheckpointIfNeeded($io, $apply, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);

                        continue;
                    }
                }

                ++$stats['noCandidateForPostalCode'];
                $this->recordUnmatchedAddress(
                    $unmatchedAddresses,
                    $addressSnapshot,
                    'no_candidate_for_postal_code',
                    $this->buildDepartmentFallbackDetail(
                        $postalCode,
                        $departmentCode,
                        $normalizedCity,
                        null !== $departmentCode ? $departmentCandidatesCache[$departmentCode] : [],
                    ),
                );
                $this->flushCheckpointIfNeeded($io, $apply, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);

                continue;
            }

            $resolution = $this->resolveMunicipalityCandidate($normalizedCity, $candidates);

            if (null === $resolution) {
                ++$stats['notFound'];
                $this->recordUnmatchedAddress(
                    $unmatchedAddresses,
                    $addressSnapshot,
                    'not_found',
                    $this->buildUnmatchedCandidatesDetail($postalCode, $normalizedCity, $candidates),
                );
                $this->flushCheckpointIfNeeded($io, $apply, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);

                continue;
            }

            ++$stats['linked'];

            if ('exact' === $resolution['status']) {
                ++$stats['exactMatched'];
            } elseif ('postal_unique' === $resolution['status']) {
                ++$stats['uniquePostalCodeMatched'];
            } else {
                ++$stats['bestCityMatched'];
            }

            if ($apply) {
                try {
                    $addressContact = $this->entityManager->find(AddressContactEntity::class, $addressSnapshot['id']);
                    $municipality = $this->entityManager->find(MunicipalityEntity::class, $resolution['municipalityId']);

                    if ($addressContact instanceof AddressContactEntity && $municipality instanceof MunicipalityEntity) {
                        $addressContact->setMunicipality($municipality);
                        $this->entityManager->persist($addressContact);
                        $pendingChangesSinceLastFlush = true;
                    } else {
                        ++$stats['errors'];
                    }
                } catch (Throwable) {
                    ++$stats['errors'];
                }
            }

            $this->flushCheckpointIfNeeded($io, $apply, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);
        }

        if ($apply && $pendingChangesSinceLastFlush) {
            $this->entityManager->flush();
            $this->entityManager->clear();
            $pendingChangesSinceLastFlush = false;
        }

        if ($stats['processed'] > $lastFlushedProcessed) {
            $this->printBatchProgress($io, $apply, $stats, $stats['processed']);
        }

        if (!$apply && [] !== $unmatchedAddresses) {
            $io->section('Adresses non matchées');
            $io->table(
                ['ID', 'Code postal', 'Ville', 'Adresse', 'Raison', 'Détail'],
                array_map(
                    static fn (array $unmatchedAddress): array => [
                        (string) $unmatchedAddress['id'],
                        $unmatchedAddress['postalCode'] ?? '—',
                        $unmatchedAddress['city'] ?? '—',
                        $unmatchedAddress['address'] ?? '—',
                        $unmatchedAddress['reason'],
                        $unmatchedAddress['details'],
                    ],
                    $unmatchedAddresses,
                ),
            );
        }

        if ($file && [] !== $unmatchedAddresses) {
            $exportPath = $this->writeUnmatchedAddressesFile($unmatchedAddresses);
            $io->note("Fichier des non matchées exporté: {$exportPath}");
        }

        $io->table(
            ['Metric', 'Count'],
            [
                ['Processed', (string) $stats['processed']],
                ['Linked', (string) $stats['linked']],
                ['Exact matches', (string) $stats['exactMatched']],
                ['Unique postal-code matches', (string) $stats['uniquePostalCodeMatched']],
                ['Best city matches', (string) $stats['bestCityMatched']],
                ['Department fallback matches', (string) $stats['departmentFallbackMatched']],
                ['Missing postal code', (string) $stats['missingPostalCode']],
                ['Missing city', (string) $stats['missingCity']],
                ['No candidate for postal code', (string) $stats['noCandidateForPostalCode']],
                ['Not found', (string) $stats['notFound']],
                ['Errors', (string) $stats['errors']],
            ],
        );

        if (!$apply) {
            $io->note('Dry-run completed. Re-run with --apply to persist changes.');
        } else {
            $io->success('Address municipality linking completed.');
        }

        return 0 === $stats['errors'] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param list<array{id:int, postalCode:?string, city:?string, address:?string, reason:string, details:string}> $unmatchedAddresses
     * @param array{id:int, postalCode:?string, city:?string, address:?string}                                      $addressSnapshot
     */
    private function recordUnmatchedAddress(
        array &$unmatchedAddresses,
        array $addressSnapshot,
        string $reason,
        string $details,
    ): void {
        $unmatchedAddresses[] = [
            'id' => $addressSnapshot['id'],
            'postalCode' => $addressSnapshot['postalCode'],
            'city' => $addressSnapshot['city'],
            'address' => $addressSnapshot['address'],
            'reason' => $reason,
            'details' => $details,
        ];
    }

    /**
     * @param list<array{id:int, postalCode:?string, city:?string, address:?string, reason:string, details:string}> $unmatchedAddresses
     */
    private function writeUnmatchedAddressesFile(array $unmatchedAddresses): string
    {
        $exportDirectory = sprintf('%s/var/export', $this->getProjectDirectory());

        if (!is_dir($exportDirectory) && !mkdir($exportDirectory, 0775, true) && !is_dir($exportDirectory)) {
            throw new RuntimeException("Unable to create export directory: {$exportDirectory}");
        }

        $filePath = sprintf(
            '%s/address-link-unmatched-%s.csv',
            $exportDirectory,
            (new DateTimeImmutable())->format('Ymd-His'),
        );

        $handle = fopen($filePath, 'wb');

        if (false === $handle) {
            throw new RuntimeException("Unable to open export file: {$filePath}");
        }

        fputcsv($handle, ['id', 'postal_code', 'city', 'address', 'reason', 'details']);

        foreach ($unmatchedAddresses as $unmatchedAddress) {
            fputcsv($handle, [
                (string) $unmatchedAddress['id'],
                $unmatchedAddress['postalCode'] ?? '',
                $unmatchedAddress['city'] ?? '',
                $unmatchedAddress['address'] ?? '',
                $unmatchedAddress['reason'],
                $unmatchedAddress['details'],
            ]);
        }

        fclose($handle);

        return $filePath;
    }

    /**
     * @return iterable<int, array{id:int, postalCode:?string, city:?string, address:?string}>
     */
    private function iterateAddressSnapshots(): iterable
    {
        $connection = $this->entityManager->getConnection();
        $lastId = 0;

        do {
            $queryBuilder = $connection->createQueryBuilder()
                ->select('address.id', 'address.postal_code', 'address.city', 'address.address')
                ->from('address_contact', 'address')
                ->andWhere('address.id > :lastId')
                ->andWhere('address.municipality_id IS NULL')
                ->setParameter('lastId', $lastId)
                ->orderBy('address.id', 'ASC')
                ->setMaxResults(self::READ_BATCH_SIZE);

            /** @var list<array{id:int|string, postal_code:?string, city:?string, address:?string}> $rows */
            $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

            foreach ($rows as $row) {
                $lastId = (int) $row['id'];

                yield [
                    'id' => (int) $row['id'],
                    'postalCode' => is_string($row['postal_code']) ? trim($row['postal_code']) : null,
                    'city' => is_string($row['city']) ? trim($row['city']) : null,
                    'address' => is_string($row['address']) ? trim($row['address']) : null,
                ];
            }
        } while ([] !== $rows);
    }

    /**
     * @param array<string, int> $stats
     */
    private function flushCheckpointIfNeeded(
        SymfonyStyle $io,
        bool $apply,
        array $stats,
        int &$lastFlushedProcessed,
        bool &$pendingChangesSinceLastFlush,
    ): void {
        if (0 === $stats['processed'] || 0 !== $stats['processed'] % self::FLUSH_BATCH_SIZE) {
            return;
        }

        if ($apply && $pendingChangesSinceLastFlush) {
            $this->entityManager->flush();
            $this->entityManager->clear();
            $pendingChangesSinceLastFlush = false;
        }

        $lastFlushedProcessed = $stats['processed'];
        $this->printBatchProgress($io, $apply, $stats, $lastFlushedProcessed);
    }

    /**
     * @param array<string, int> $stats
     */
    private function printBatchProgress(
        SymfonyStyle $io,
        bool $apply,
        array $stats,
        int $processed,
    ): void {
        $batchNumber = (int) ceil($processed / self::FLUSH_BATCH_SIZE);
        $label = $apply ? 'persisted' : 'processed';

        $io->text(sprintf(
            'Batch %d done | %s=%d | linked=%d',
            $batchNumber,
            $label,
            $processed,
            $stats['linked'],
        ));
    }

    /**
     * @return list<array{id:int, normalizedName:string}>
     */
    private function loadMunicipalityCandidatesByPostalCode(string $postalCode): array
    {
        $connection = $this->entityManager->getConnection();

        /** @var list<array{id:int|string, name:string}> $rows */
        $rows = $connection->fetchAllAssociative(
            'SELECT municipality.id, municipality.name
            FROM municipality
            WHERE municipality.postal_code = :postalCode',
            [
                'postalCode' => $postalCode,
            ],
            [
                'postalCode' => ParameterType::STRING,
            ],
        );

        $candidates = [];

        foreach ($rows as $row) {
            $name = trim((string) $row['name']);

            if ('' === $name) {
                continue;
            }

            $candidates[] = [
                'id' => (int) $row['id'],
                'normalizedName' => $this->normalizeCommune($name),
            ];
        }

        return $candidates;
    }

    /**
     * @return list<array{id:int, normalizedName:string}>
     */
    private function loadMunicipalityCandidatesByDepartmentCode(string $departmentCode): array
    {
        $connection = $this->entityManager->getConnection();

        /** @var list<array{id:int|string, name:string}> $rows */
        $rows = $connection->fetchAllAssociative(
            'SELECT municipality.id, municipality.name
            FROM municipality
            INNER JOIN department ON department.id = municipality.department_id
            WHERE department.code = :departmentCode',
            [
                'departmentCode' => $departmentCode,
            ],
            [
                'departmentCode' => ParameterType::STRING,
            ],
        );

        $candidates = [];

        foreach ($rows as $row) {
            $name = trim((string) $row['name']);

            if ('' === $name) {
                continue;
            }

            $candidates[] = [
                'id' => (int) $row['id'],
                'normalizedName' => $this->normalizeCommune($name),
            ];
        }

        return $candidates;
    }

    /**
     * @param list<array{id:int, normalizedName:string}> $candidates
     *
     * @return array{status:'exact'|'postal_unique'|'best_city', municipalityId:int}|null
     */
    private function resolveMunicipalityCandidate(string $normalizedCity, array $candidates): ?array
    {
        $exactCandidates = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['normalizedName'] === $normalizedCity,
        ));

        if (1 === count($exactCandidates)) {
            return [
                'status' => 'exact',
                'municipalityId' => $exactCandidates[0]['id'],
            ];
        }

        if (1 === count($candidates)) {
            return [
                'status' => 'postal_unique',
                'municipalityId' => $candidates[0]['id'],
            ];
        }

        $bestCandidate = null;

        foreach ($candidates as $candidate) {
            if ('' === $candidate['normalizedName']) {
                continue;
            }

            similar_text($normalizedCity, $candidate['normalizedName'], $score);
            $candidateScore = (int) round($score);
            $candidateData = [
                'id' => $candidate['id'],
                'score' => $candidateScore,
            ];

            if (null === $bestCandidate
                || $candidateData['score'] > $bestCandidate['score']
                || ($candidateData['score'] === $bestCandidate['score'] && $candidateData['id'] < $bestCandidate['id'])) {
                $bestCandidate = $candidateData;
            }
        }

        if (null === $bestCandidate) {
            return null;
        }

        return [
            'status' => 'best_city',
            'municipalityId' => $bestCandidate['id'],
        ];
    }

    /**
     * @param list<array{id:int, normalizedName:string}> $candidates
     *
     * @return array{status:'department_fallback', municipalityId:int}|null
     */
    private function resolveMunicipalityCandidateWithinDepartment(string $normalizedCity, array $candidates): ?array
    {
        $exactCandidates = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['normalizedName'] === $normalizedCity,
        ));

        if (1 === count($exactCandidates)) {
            return [
                'status' => 'department_fallback',
                'municipalityId' => $exactCandidates[0]['id'],
            ];
        }

        $containingCandidates = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => '' !== $candidate['normalizedName']
                && (str_contains($candidate['normalizedName'], $normalizedCity) || str_contains($normalizedCity, $candidate['normalizedName'])),
        ));

        if (1 === count($containingCandidates)) {
            return [
                'status' => 'department_fallback',
                'municipalityId' => $containingCandidates[0]['id'],
            ];
        }

        $scoredCandidates = [];

        foreach ($candidates as $candidate) {
            if ('' === $candidate['normalizedName']) {
                continue;
            }

            similar_text($normalizedCity, $candidate['normalizedName'], $score);
            $scoredCandidates[] = [
                'id' => $candidate['id'],
                'score' => (int) round($score),
            ];
        }

        if ([] === $scoredCandidates) {
            return null;
        }

        usort($scoredCandidates, static function (array $leftCandidate, array $rightCandidate): int {
            if ($leftCandidate['score'] === $rightCandidate['score']) {
                return $leftCandidate['id'] <=> $rightCandidate['id'];
            }

            return $rightCandidate['score'] <=> $leftCandidate['score'];
        });

        $bestCandidate = $scoredCandidates[0];
        $secondBestScore = $scoredCandidates[1]['score'] ?? 0;

        if (75 > $bestCandidate['score']) {
            return null;
        }

        if (90 > $bestCandidate['score'] && $bestCandidate['score'] - $secondBestScore < 8) {
            return null;
        }

        return [
            'status' => 'department_fallback',
            'municipalityId' => $bestCandidate['id'],
        ];
    }

    /**
     * @param array{postalCode:?string, address:?string} $addressSnapshot
     */
    private function resolvePostalCode(array $addressSnapshot): ?string
    {
        $postalCode = $addressSnapshot['postalCode'];

        if (is_string($postalCode) && preg_match('/^\d{5}$/', $postalCode)) {
            return $postalCode;
        }

        $address = $addressSnapshot['address'];

        if (!is_string($address) || 1 !== preg_match('/\b(\d{5})\b/', $address, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function inferDepartmentCodeFromPostalCode(string $postalCode): ?string
    {
        if (!preg_match('/^\d{5}$/', $postalCode)) {
            return null;
        }

        return substr($postalCode, 0, 2);
    }

    private function normalizeCommune(?string $value): string
    {
        $normalized = $this->normalizeText($value);

        if ('' === $normalized) {
            return '';
        }

        $normalized = preg_replace('/\bcedex(?:\s+\d+)?\b/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bsaint\b/', 'st', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bsainte\b/', 'ste', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @param list<array{id:int, normalizedName:string}> $candidates
     */
    private function buildUnmatchedCandidatesDetail(string $postalCode, string $normalizedCity, array $candidates): string
    {
        $candidateNames = array_values(array_filter(array_map(
            static fn (array $candidate): string => $candidate['normalizedName'],
            $candidates,
        )));

        $candidatesSummary = [] === $candidateNames ? 'aucun libellé exploitable' : implode(', ', $candidateNames);

        return "Ville normalisée: {$normalizedCity}. Code postal: {$postalCode}. Candidats: {$candidatesSummary}.";
    }

    /**
     * @param list<array{id:int, normalizedName:string}> $departmentCandidates
     */
    private function buildDepartmentFallbackDetail(
        string $postalCode,
        ?string $departmentCode,
        string $normalizedCity,
        array $departmentCandidates,
    ): string {
        if (null === $departmentCode) {
            return "Aucune commune trouvée pour le code postal {$postalCode}, et département impossible à déduire.";
        }

        $departmentCandidateNames = array_values(array_filter(array_map(
            static fn (array $candidate): string => $candidate['normalizedName'],
            $departmentCandidates,
        )));

        $matchingDepartmentCandidates = array_values(array_filter(
            $departmentCandidateNames,
            static fn (string $candidateName): bool => str_contains($candidateName, $normalizedCity) || str_contains($normalizedCity, $candidateName),
        ));

        $matchingSummary = [] === $matchingDepartmentCandidates ? 'aucun candidat proche' : implode(', ', array_slice($matchingDepartmentCandidates, 0, 10));

        return "Aucune commune trouvée pour le code postal {$postalCode}. Repli sur département {$departmentCode}. Ville normalisée: {$normalizedCity}. Candidats proches: {$matchingSummary}.";
    }

    private function getProjectDirectory(): string
    {
        return dirname(__DIR__, 2);
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
}
