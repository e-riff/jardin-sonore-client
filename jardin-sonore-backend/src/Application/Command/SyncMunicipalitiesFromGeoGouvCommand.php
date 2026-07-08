<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Geography\MunicipalityGeoGouvSyncReaderInterface;
use App\Application\Geography\MunicipalityGeoGouvSyncWriterInterface;
use App\Application\Geography\MunicipalitySyncSnapshot;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
#[AsCommand(
    name: 'app:municipality:sync-geo-gouv',
    description: 'Synchronize municipalities from geo.api.gouv.fr using the INSEE code.',
)]
final class SyncMunicipalitiesFromGeoGouvCommand extends Command
{
    private const string GEO_GOUV_COMMUNE_ENDPOINT = 'https://geo.api.gouv.fr/communes/%s?fields=nom,code,siren,codesPostaux,centre';
    private const int HTTP_TIMEOUT_SECONDS = 15;
    private const int FLUSH_BATCH_SIZE = 50;
    private const int READ_BATCH_SIZE = 100;

    public function __construct(
        private readonly MunicipalityGeoGouvSyncReaderInterface $municipalityGeoGouvSyncReader,
        private readonly MunicipalityGeoGouvSyncWriterInterface $municipalityGeoGouvSyncWriter,
    ) {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'Optional INSEE code to sync only one municipality.')]
        ?string $insee = null,
        #[Option(description: 'Persist changes instead of running a dry-run.')]
        bool $apply = false,
        #[Option(description: 'Optional municipality offset.')]
        int $offset = 0,
        #[Option(description: 'Optional municipality limit.')]
        ?int $limit = null,
        #[Option(description: 'Synchronize center latitude/longitude.')]
        bool $withCenter = true,
        #[Option(description: 'Synchronize the municipality name.')]
        bool $withName = true,
    ): int {
        ini_set('memory_limit', '512M');

        $io = new SymfonyStyle($input, $output);
        $offset = max(0, $offset);
        $limit = null !== $limit ? max(1, $limit) : null;
        $inseeCode = trim((string) $insee);

        $stats = [
            'processed' => 0,
            'updated' => 0,
            'postalCodeChanged' => 0,
            'nameChanged' => 0,
            'sirenChanged' => 0,
            'centerChanged' => 0,
            'unchanged' => 0,
            'missingInsee' => 0,
            'notFound' => 0,
            'errors' => 0,
        ];
        $lastFlushedProcessed = 0;
        $pendingChangesSinceLastFlush = false;

        $hasAnyMunicipality = false;

        foreach ($this->municipalityGeoGouvSyncReader->iterateMunicipalitySnapshots($inseeCode, $offset, $limit) as $municipalitySnapshot) {
            $hasAnyMunicipality = true;
            ++$stats['processed'];

            $municipalityInseeCode = $municipalitySnapshot->inseeCode;
            if (null === $municipalityInseeCode || '' === trim($municipalityInseeCode)) {
                ++$stats['missingInsee'];
                $io->warning(sprintf('Municipality #%d skipped: missing INSEE code.', $municipalitySnapshot->id));
                $this->flushCheckpointIfNeeded($io, $apply, $offset, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);

                continue;
            }

            try {
                $payload = $this->fetchCommunePayload($municipalityInseeCode);
            } catch (RuntimeException $runtimeException) {
                ++$stats['errors'];
                $io->warning(sprintf('%s (%s)', $runtimeException->getMessage(), $municipalityInseeCode));
                $this->flushCheckpointIfNeeded($io, $apply, $offset, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);

                continue;
            }

            if (null === $payload) {
                ++$stats['notFound'];
                $io->warning(sprintf('No commune returned by geo.api.gouv.fr for INSEE %s.', $municipalityInseeCode));
                $this->flushCheckpointIfNeeded($io, $apply, $offset, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);

                continue;
            }

            $changes = $this->computeMunicipalityChanges($municipalitySnapshot, $payload, $withCenter, $withName);

            if ([] === $changes) {
                ++$stats['unchanged'];
                $this->flushCheckpointIfNeeded($io, $apply, $offset, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);

                continue;
            }

            ++$stats['updated'];
            $stats['postalCodeChanged'] += (int) isset($changes['postalCode']);
            $stats['nameChanged'] += (int) isset($changes['name']);
            $stats['sirenChanged'] += (int) isset($changes['siren']);
            $stats['centerChanged'] += (int) isset($changes['center']);

            if ($apply) {
                if ($this->municipalityGeoGouvSyncWriter->applyChanges($municipalitySnapshot->id, $changes)) {
                    $pendingChangesSinceLastFlush = true;
                }
            }

            $this->flushCheckpointIfNeeded($io, $apply, $offset, $stats, $lastFlushedProcessed, $pendingChangesSinceLastFlush);
        }

        if (!$hasAnyMunicipality) {
            $io->warning('No municipality found for this selection.');

            return Command::SUCCESS;
        }

        if ($apply) {
            if ($pendingChangesSinceLastFlush) {
                $this->municipalityGeoGouvSyncWriter->flush();
                $this->municipalityGeoGouvSyncWriter->clear();
                $pendingChangesSinceLastFlush = false;
            }

            if ($stats['processed'] > $lastFlushedProcessed) {
                $lastFlushedProcessed = $stats['processed'];
                $batchNumber = (int) ceil($lastFlushedProcessed / self::FLUSH_BATCH_SIZE);
                $io->text(sprintf(
                    'Flush batch %d done | persisted=%d | updated=%d | safe restart --offset=%d',
                    $batchNumber,
                    $lastFlushedProcessed,
                    $stats['updated'],
                    $offset + $lastFlushedProcessed,
                ));
            }
        }

        if (!$apply && 0 !== $stats['processed'] % self::FLUSH_BATCH_SIZE) {
            $completedBatches = (int) ceil($stats['processed'] / self::FLUSH_BATCH_SIZE);
            $io->text(sprintf(
                'Batch %d done | processed=%d | updated=%d | next --offset=%d',
                $completedBatches,
                $stats['processed'],
                $stats['updated'],
                $offset + $stats['processed'],
            ));
        }

        $io->table(
            ['Metric', 'Count'],
            [
                ['Processed', (string) $stats['processed']],
                ['Updated', (string) $stats['updated']],
                ['Postal codes changed', (string) $stats['postalCodeChanged']],
                ['Names changed', (string) $stats['nameChanged']],
                ['Siren changed', (string) $stats['sirenChanged']],
                ['Center changed', (string) $stats['centerChanged']],
                ['Unchanged', (string) $stats['unchanged']],
                ['Missing INSEE', (string) $stats['missingInsee']],
                ['Not found on API', (string) $stats['notFound']],
                ['Errors', (string) $stats['errors']],
            ],
        );

        $io->note('SIRET is not synchronized because geo.api.gouv.fr communes does not expose it.');

        if (!$apply) {
            $io->note('Dry-run completed. Re-run with --apply to persist changes.');
        } else {
            $io->success('Municipality synchronization completed.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, int> $stats
     */
    private function flushCheckpointIfNeeded(
        SymfonyStyle $io,
        bool $apply,
        int $offset,
        array $stats,
        int &$lastFlushedProcessed,
        bool &$pendingChangesSinceLastFlush,
    ): void {
        if (0 !== $stats['processed'] % self::FLUSH_BATCH_SIZE) {
            return;
        }

        if ($apply && $pendingChangesSinceLastFlush) {
            $this->municipalityGeoGouvSyncWriter->flush();
            $this->municipalityGeoGouvSyncWriter->clear();
            $pendingChangesSinceLastFlush = false;
        }

        $lastFlushedProcessed = $stats['processed'];
        $batchNumber = (int) ($lastFlushedProcessed / self::FLUSH_BATCH_SIZE);
        $io->text(sprintf(
            'Flush batch %d done | persisted=%d | updated=%d | safe restart --offset=%d',
            $batchNumber,
            $lastFlushedProcessed,
            $stats['updated'],
            $offset + $lastFlushedProcessed,
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchCommunePayload(string $inseeCode): ?array
    {
        $url = sprintf(self::GEO_GOUV_COMMUNE_ENDPOINT, rawurlencode($inseeCode));
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::HTTP_TIMEOUT_SECONDS,
                'header' => "Accept: application/json\r\nUser-Agent: jardin-sonore-backend/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        if (false === $responseBody) {
            throw new RuntimeException(sprintf('Unable to reach geo.api.gouv.fr for INSEE %s.', $inseeCode));
        }

        $statusCode = $this->extractHttpStatusCode($http_response_header);
        if (404 === $statusCode) {
            return null;
        }

        if (null !== $statusCode && 400 <= $statusCode) {
            throw new RuntimeException(sprintf('geo.api.gouv.fr returned HTTP %d for INSEE %s.', $statusCode, $inseeCode));
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new RuntimeException(sprintf('Invalid JSON returned for INSEE %s: %s', $inseeCode, $jsonException->getMessage()), 0, $jsonException);
        }

        return [] === $payload ? null : $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function computeMunicipalityChanges(
        MunicipalitySyncSnapshot $municipalitySnapshot,
        array $payload,
        bool $withCenter,
        bool $withName,
    ): array {
        $changes = [];

        if ($withName) {
            $apiName = $this->nullableString($payload['nom'] ?? null);
            if (null !== $apiName && $apiName !== $municipalitySnapshot->name) {
                $changes['name'] = sprintf('name: "%s" -> "%s"', $municipalitySnapshot->name, $apiName);
                $changes['nameValue'] = $apiName;
            }
        }

        $apiPostalCode = $this->resolvePostalCode($municipalitySnapshot->postalCode, $payload['codesPostaux'] ?? null);
        if ($apiPostalCode !== $municipalitySnapshot->postalCode) {
            $changes['postalCode'] = sprintf('postalCode: %s -> %s', $municipalitySnapshot->postalCode ?? 'null', $apiPostalCode ?? 'null');
            $changes['postalCodeValue'] = $apiPostalCode;
        }

        $apiSiren = $this->nullableString($payload['siren'] ?? null);
        if ($apiSiren !== $municipalitySnapshot->siren) {
            $changes['siren'] = sprintf('siren: %s -> %s', $municipalitySnapshot->siren ?? 'null', $apiSiren ?? 'null');
            $changes['sirenValue'] = $apiSiren;
        }

        if ($withCenter) {
            [$centerLatitude, $centerLongitude] = $this->extractCenterCoordinates($payload['centre'] ?? null);

            if ($centerLatitude !== $municipalitySnapshot->centerLatitude || $centerLongitude !== $municipalitySnapshot->centerLongitude) {
                $changes['center'] = sprintf(
                    'center: (%s, %s) -> (%s, %s)',
                    $municipalitySnapshot->centerLatitude ?? 'null',
                    $municipalitySnapshot->centerLongitude ?? 'null',
                    $centerLatitude ?? 'null',
                    $centerLongitude ?? 'null',
                );
                $changes['centerLatitudeValue'] = $centerLatitude;
                $changes['centerLongitudeValue'] = $centerLongitude;
            }
        }

        return $changes;
    }

    private function resolvePostalCode(?string $currentPostalCode, mixed $codesPostaux): ?string
    {
        if (!is_array($codesPostaux)) {
            return $currentPostalCode;
        }

        $normalizedPostalCodes = array_values(array_filter(array_map(
            fn (mixed $postalCode): ?string => $this->nullableString($postalCode),
            $codesPostaux,
        )));

        if ([] === $normalizedPostalCodes) {
            return $currentPostalCode;
        }

        if (null !== $currentPostalCode && in_array($currentPostalCode, $normalizedPostalCodes, true)) {
            return $currentPostalCode;
        }

        return $normalizedPostalCodes[0];
    }

    /**
     * @return array{?float, ?float}
     */
    private function extractCenterCoordinates(mixed $centre): array
    {
        if (!is_array($centre) || !is_array($centre['coordinates'] ?? null)) {
            return [null, null];
        }

        $coordinates = $centre['coordinates'];
        $longitude = isset($coordinates[0]) && is_numeric($coordinates[0]) ? (float) $coordinates[0] : null;
        $latitude = isset($coordinates[1]) && is_numeric($coordinates[1]) ? (float) $coordinates[1] : null;

        return [$latitude, $longitude];
    }

    /**
     * @param list<string> $responseHeaders
     */
    private function extractHttpStatusCode(array $responseHeaders): ?int
    {
        foreach ($responseHeaders as $responseHeader) {
            if (1 === preg_match('#^HTTP/\S+\s+(\d{3})#', $responseHeader, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
