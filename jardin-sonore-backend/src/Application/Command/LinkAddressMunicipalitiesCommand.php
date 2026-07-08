<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Geography\AddressContactSnapshot;
use App\Application\Geography\AddressMunicipalityCandidate;
use App\Application\Geography\AddressMunicipalityLinkingWriterInterface;
use App\Application\Geography\AddressMunicipalityLinkingReaderInterface;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:address:link-municipalities',
    description: 'Link address contacts to municipalities using postal code and city matching.',
)]
final readonly class LinkAddressMunicipalitiesCommand
{
    private const int FLUSH_BATCH_SIZE = 100;

    public function __construct(
        private AddressMunicipalityLinkingReaderInterface $addressMunicipalityLinkingReader,
        private AddressMunicipalityLinkingWriterInterface $addressMunicipalityLinkingWriter,
    ) {
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

        foreach ($this->addressMunicipalityLinkingReader->iterateUnlinkedAddressSnapshots() as $addressSnapshot) {
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

            $normalizedCity = $this->normalizeCommune($addressSnapshot->city);
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

            $postalCodeCandidatesCache[$postalCode] ??= $this->addressMunicipalityLinkingReader->findMunicipalityCandidatesByPostalCode($postalCode);
            $candidates = $postalCodeCandidatesCache[$postalCode];

            if ([] === $candidates) {
                $departmentCode = $this->inferDepartmentCodeFromPostalCode($postalCode);

                if (null !== $departmentCode) {
                    $departmentCandidatesCache[$departmentCode] ??= $this->addressMunicipalityLinkingReader->findMunicipalityCandidatesByDepartmentCode($departmentCode);
                    $resolution = $this->resolveMunicipalityCandidateWithinDepartment($normalizedCity, $departmentCandidatesCache[$departmentCode]);

                    if (null !== $resolution) {
                        ++$stats['linked'];
                        ++$stats['departmentFallbackMatched'];

                        if ($apply && $this->addressMunicipalityLinkingWriter->linkAddressContactToMunicipality($addressSnapshot->id, $resolution['municipalityId'])) {
                            $pendingChangesSinceLastFlush = true;
                        } elseif ($apply) {
                            ++$stats['errors'];
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

            if ($apply && $this->addressMunicipalityLinkingWriter->linkAddressContactToMunicipality($addressSnapshot->id, $resolution['municipalityId'])) {
                $pendingChangesSinceLastFlush = true;
            } elseif ($apply) {
                ++$stats['errors'];
            }

            $this->flushCheckpointIfNeeded($io, $apply, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);
        }

        if ($apply && $pendingChangesSinceLastFlush) {
            $this->addressMunicipalityLinkingWriter->flush();
            $this->addressMunicipalityLinkingWriter->clear();
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
     */
    private function recordUnmatchedAddress(
        array &$unmatchedAddresses,
        AddressContactSnapshot $addressSnapshot,
        string $reason,
        string $details,
    ): void {
        $unmatchedAddresses[] = [
            'id' => $addressSnapshot->id,
            'postalCode' => $addressSnapshot->postalCode,
            'city' => $addressSnapshot->city,
            'address' => $addressSnapshot->address,
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
            $this->addressMunicipalityLinkingWriter->flush();
            $this->addressMunicipalityLinkingWriter->clear();
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
     * @param list<AddressMunicipalityCandidate> $candidates
     *
     * @return array{status:'exact'|'postal_unique'|'best_city', municipalityId:int}|null
     */
    private function resolveMunicipalityCandidate(string $normalizedCity, array $candidates): ?array
    {
        $exactCandidates = array_values(array_filter(
            $candidates,
            fn (AddressMunicipalityCandidate $candidate): bool => $this->normalizeCommune($candidate->name) === $normalizedCity,
        ));

        if (1 === count($exactCandidates)) {
            return [
                'status' => 'exact',
                'municipalityId' => $exactCandidates[0]->municipalityId,
            ];
        }

        if (1 === count($candidates)) {
            return [
                'status' => 'postal_unique',
                'municipalityId' => $candidates[0]->municipalityId,
            ];
        }

        $bestCandidate = null;

        foreach ($candidates as $candidate) {
            $normalizedCandidateName = $this->normalizeCommune($candidate->name);

            if ('' === $normalizedCandidateName) {
                continue;
            }

            similar_text($normalizedCity, $normalizedCandidateName, $score);
            $candidateScore = (int) round($score);
            $candidateData = [
                'id' => $candidate->municipalityId,
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
     * @param list<AddressMunicipalityCandidate> $candidates
     *
     * @return array{status:'department_fallback', municipalityId:int}|null
     */
    private function resolveMunicipalityCandidateWithinDepartment(string $normalizedCity, array $candidates): ?array
    {
        $exactCandidates = array_values(array_filter(
            $candidates,
            fn (AddressMunicipalityCandidate $candidate): bool => $this->normalizeCommune($candidate->name) === $normalizedCity,
        ));

        if (1 === count($exactCandidates)) {
            return [
                'status' => 'department_fallback',
                'municipalityId' => $exactCandidates[0]->municipalityId,
            ];
        }

        $containingCandidates = array_values(array_filter(
            $candidates,
            fn (AddressMunicipalityCandidate $candidate): bool => '' !== $this->normalizeCommune($candidate->name)
                && (str_contains($this->normalizeCommune($candidate->name), $normalizedCity) || str_contains($normalizedCity, $this->normalizeCommune($candidate->name))),
        ));

        if (1 === count($containingCandidates)) {
            return [
                'status' => 'department_fallback',
                'municipalityId' => $containingCandidates[0]->municipalityId,
            ];
        }

        $scoredCandidates = [];

        foreach ($candidates as $candidate) {
            $normalizedCandidateName = $this->normalizeCommune($candidate->name);

            if ('' === $normalizedCandidateName) {
                continue;
            }

            similar_text($normalizedCity, $normalizedCandidateName, $score);
            $scoredCandidates[] = [
                'id' => $candidate->municipalityId,
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

    private function resolvePostalCode(AddressContactSnapshot $addressSnapshot): ?string
    {
        $postalCode = $addressSnapshot->postalCode;

        if (is_string($postalCode) && preg_match('/^\d{5}$/', $postalCode)) {
            return $postalCode;
        }

        $address = $addressSnapshot->address;

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
     * @param list<AddressMunicipalityCandidate> $candidates
     */
    private function buildUnmatchedCandidatesDetail(string $postalCode, string $normalizedCity, array $candidates): string
    {
        $candidateNames = array_values(array_filter(array_map(
            fn (AddressMunicipalityCandidate $candidate): string => $this->normalizeCommune($candidate->name),
            $candidates,
        )));

        $candidatesSummary = [] === $candidateNames ? 'aucun libellé exploitable' : implode(', ', $candidateNames);

        return "Ville normalisée: {$normalizedCity}. Code postal: {$postalCode}. Candidats: {$candidatesSummary}.";
    }

    /**
     * @param list<AddressMunicipalityCandidate> $departmentCandidates
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
            fn (AddressMunicipalityCandidate $candidate): string => $this->normalizeCommune($candidate->name),
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
