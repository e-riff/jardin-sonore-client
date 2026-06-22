<?php

declare(strict_types=1);

namespace App\Application\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:geo:compute-municipality-centers',
    description: 'Compute municipality center coordinates from stored GeoJSON shapes.',
)]
final readonly class ComputeMunicipalityCentersCommand
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Force recomputing municipalities that already have center coordinates.')]
        bool $force = false,
        #[Option(description: 'Flush size used while updating municipalities.')]
        int $batchSize = 100,
    ): int {
        if ($batchSize < 1) {
            $io->error('Batch size must be greater than zero.');

            return Command::FAILURE;
        }

        $connection = $this->entityManager->getConnection();
        $updated = 0;
        $ignored = 0;
        $errors = 0;
        $lastProcessedId = 0;

        do {
            $sql = 'SELECT id, geo_shape FROM municipality WHERE id > :lastProcessedId AND geo_shape IS NOT NULL';

            if (false === $force) {
                $sql .= ' AND center_latitude IS NULL AND center_longitude IS NULL';
            }

            $sql .= sprintf(' ORDER BY id ASC LIMIT %d', $batchSize);

            /** @var list<array{id: int|string, geo_shape: string|null}> $municipalityRows */
            $municipalityRows = $connection->executeQuery($sql, [
                'lastProcessedId' => $lastProcessedId,
            ])->fetchAllAssociative();

            foreach ($municipalityRows as $municipalityRow) {
                $lastProcessedId = (int) $municipalityRow['id'];
                $center = $this->computeCenterFromJson($municipalityRow['geo_shape']);

                if (null === $center) {
                    ++$ignored;
                } else {
                    try {
                        $connection->executeStatement(
                            'UPDATE municipality SET center_latitude = :centerLatitude, center_longitude = :centerLongitude WHERE id = :id',
                            [
                                'centerLatitude' => $center['latitude'],
                                'centerLongitude' => $center['longitude'],
                                'id' => $lastProcessedId,
                            ],
                        );
                        ++$updated;
                    } catch (\Throwable) {
                        ++$errors;
                    }
                }
            }
        } while ([] !== $municipalityRows);

        $io->success(sprintf(
            'Municipality centers computed. Updated: %d. Ignored: %d. Flush errors: %d.',
            $updated,
            $ignored,
            $errors,
        ));

        return 0 === $errors ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function computeCenterFromJson(?string $geoShape): ?array
    {
        if (null === $geoShape) {
            return null;
        }

        try {
            $decodedGeoShape = json_decode($geoShape, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decodedGeoShape)) {
            return null;
        }

        $points = [];
        $this->collectPoints($decodedGeoShape['coordinates'] ?? null, $points);

        if ([] === $points) {
            return null;
        }

        $longitudeSum = 0.0;
        $latitudeSum = 0.0;

        foreach ($points as $point) {
            $longitudeSum += $point['longitude'];
            $latitudeSum += $point['latitude'];
        }

        return [
            'longitude' => $longitudeSum / count($points),
            'latitude' => $latitudeSum / count($points),
        ];
    }

    /**
     * @param list<array{latitude: float, longitude: float}> $points
     */
    private function collectPoints(mixed $coordinates, array &$points): void
    {
        if (!is_array($coordinates)) {
            return;
        }

        if (
            2 <= count($coordinates)
            && is_numeric($coordinates[0] ?? null)
            && is_numeric($coordinates[1] ?? null)
        ) {
            $points[] = [
                'longitude' => (float) $coordinates[0],
                'latitude' => (float) $coordinates[1],
            ];

            return;
        }

        foreach ($coordinates as $nestedCoordinates) {
            $this->collectPoints($nestedCoordinates, $points);
        }
    }
}
