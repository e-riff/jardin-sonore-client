<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use Doctrine\ORM\EntityManagerInterface;
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
    name: 'app:municipality:sync-geo-shape',
    description: 'Synchronize one municipality GeoJSON shape from geo.api.gouv.fr using its INSEE code.',
)]
final class SyncMunicipalityGeoShapeCommand extends Command
{
    private const string GEO_GOUV_COMMUNE_ENDPOINT = 'https://geo.api.gouv.fr/communes/%s?fields=nom,centre,contour';
    private const int HTTP_TIMEOUT_SECONDS = 15;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'Municipality INSEE code.')]
        string $inseeCode,
        #[Option(description: 'Persist the new GeoJSON shape and center coordinates.')]
        bool $apply = false,
        #[Option(description: 'Also synchronize center latitude/longitude from the API response.')]
        bool $withCenter = true,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $normalizedInseeCode = strtoupper(trim($inseeCode));

        if (1 !== preg_match('/^[0-9A-Z]{5}$/', $normalizedInseeCode)) {
            $io->error(sprintf('Invalid INSEE code "%s".', $inseeCode));

            return Command::INVALID;
        }

        $municipalityEntity = $this->entityManager->getRepository(MunicipalityEntity::class)->findOneBy([
            'inseeCode' => $normalizedInseeCode,
        ]);

        if (!$municipalityEntity instanceof MunicipalityEntity) {
            $io->error(sprintf('No municipality found locally for INSEE %s.', $normalizedInseeCode));

            return Command::FAILURE;
        }

        try {
            $payload = $this->fetchCommunePayload($normalizedInseeCode);
        } catch (RuntimeException $runtimeException) {
            $io->error($runtimeException->getMessage());

            return Command::FAILURE;
        }

        if (null === $payload) {
            $io->error(sprintf('No commune returned by geo.api.gouv.fr for INSEE %s.', $normalizedInseeCode));

            return Command::FAILURE;
        }

        $contour = $this->extractContour($payload);

        if (null === $contour) {
            $io->error(sprintf('No valid contour returned by geo.api.gouv.fr for INSEE %s.', $normalizedInseeCode));

            return Command::FAILURE;
        }

        [$centerLatitude, $centerLongitude] = $this->extractCenterCoordinates($payload['centre'] ?? null);
        $shapeChanged = $municipalityEntity->getGeoShape() !== $contour;
        $centerChanged = $withCenter
            && ($municipalityEntity->getCenterLatitude() !== $centerLatitude || $municipalityEntity->getCenterLongitude() !== $centerLongitude);

        $io->definitionList(
            ['Commune' => sprintf('%s — %s', $normalizedInseeCode, $municipalityEntity->getName())],
            ['Contour API' => (string) ($contour['type'] ?? 'unknown')],
            ['Centre API' => null !== $centerLatitude && null !== $centerLongitude ? sprintf('%F, %F', $centerLatitude, $centerLongitude) : 'absent'],
            ['Geo shape changed' => $shapeChanged ? 'yes' : 'no'],
            ['Center changed' => $centerChanged ? 'yes' : 'no'],
        );

        if (!$shapeChanged && !$centerChanged) {
            $io->success('The local municipality geometry is already synchronized.');

            return Command::SUCCESS;
        }

        if (!$apply) {
            $io->note('Dry-run completed. Re-run with --apply to persist changes.');

            return Command::SUCCESS;
        }

        $municipalityEntity->setGeoShape($contour);

        if ($withCenter) {
            $municipalityEntity
                ->setCenterLatitude($centerLatitude)
                ->setCenterLongitude($centerLongitude);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Municipality %s synchronized successfully.', $normalizedInseeCode));

        return Command::SUCCESS;
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
     * @return array<string, mixed>|null
     */
    private function extractContour(array $payload): ?array
    {
        $contour = $payload['contour'] ?? $payload['geometry'] ?? null;

        if (!is_array($contour) || !is_string($contour['type'] ?? null) || !is_array($contour['coordinates'] ?? null)) {
            return null;
        }

        if (!$this->hasValidGeoJsonCoordinates($contour['coordinates'])) {
            return null;
        }

        return $contour;
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

    /**
     * @param array<string, mixed>|list<mixed> $coordinates
     */
    private function hasValidGeoJsonCoordinates(array $coordinates): bool
    {
        if ([] === $coordinates) {
            return false;
        }

        if (array_key_exists(0, $coordinates) && array_key_exists(1, $coordinates)
            && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
            return true;
        }

        foreach ($coordinates as $childCoordinates) {
            if (!is_array($childCoordinates) || !$this->hasValidGeoJsonCoordinates($childCoordinates)) {
                return false;
            }
        }

        return true;
    }
}
