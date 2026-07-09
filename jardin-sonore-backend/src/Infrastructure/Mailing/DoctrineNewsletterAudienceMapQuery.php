<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\AudienceMapMunicipalityShape;
use App\Application\Mailing\NewsletterAudienceMapQueryInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

final readonly class DoctrineNewsletterAudienceMapQuery implements NewsletterAudienceMapQueryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findMunicipalityShapesByInseeCodes(array $inseeCodes, ?int $limit = null): array
    {
        $inseeCodes = $this->normalizeInseeCodes($inseeCodes);

        if ([] === $inseeCodes) {
            return [];
        }

        if (null !== $limit && $limit < 1) {
            throw new InvalidArgumentException('Municipality shape query limit must be greater than zero.');
        }

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('municipality.insee_code', 'municipality.name', 'municipality.postal_code', 'municipality.geo_shape')
            ->from('municipality', 'municipality')
            ->where('municipality.insee_code IN (:inseeCodes)')
            ->andWhere('municipality.geo_shape IS NOT NULL')
            ->setParameter('inseeCodes', $inseeCodes, ArrayParameterType::STRING)
            ->orderBy('municipality.name', 'ASC')
            ->addOrderBy('municipality.postal_code', 'ASC');

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        /** @var list<array{
         *     insee_code: string,
         *     name: string,
         *     postal_code: ?string,
         *     geo_shape: string|array<string, mixed>|list<mixed>
         * }> $rows
         */
        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();
        $shapes = [];

        foreach ($rows as $row) {
            $geoShape = $row['geo_shape'];

            if (is_string($geoShape)) {
                $decodedGeoShape = json_decode($geoShape, true);
                $geoShape = is_array($decodedGeoShape) ? $decodedGeoShape : null;
            }

            if (!is_array($geoShape)) {
                continue;
            }

            $shapes[] = new AudienceMapMunicipalityShape(
                inseeCode: $row['insee_code'],
                label: $this->municipalityLabel($row['name'], $row['postal_code']),
                geoShape: $geoShape,
            );
        }

        return $shapes;
    }

    public function findMunicipalityChoicesWithinPolygon(array $polygonPoints): array
    {
        $polygonPoints = $this->normalizePolygonPoints($polygonPoints);

        if (count($polygonPoints) < 3) {
            return [];
        }

        $latitudes = array_column($polygonPoints, 'lat');
        $longitudes = array_column($polygonPoints, 'lng');
        $minLatitude = min($latitudes);
        $maxLatitude = max($latitudes);
        $minLongitude = min($longitudes);
        $maxLongitude = max($longitudes);

        /** @var list<array{
         *     insee_code: string,
         *     name: string,
         *     postal_code: ?string,
         *     center_latitude: float|int|string,
         *     center_longitude: float|int|string
         * }> $rows
         */
        $rows = $this->connection->createQueryBuilder()
            ->select(
                'municipality.insee_code',
                'municipality.name',
                'municipality.postal_code',
                'municipality.center_latitude',
                'municipality.center_longitude',
            )
            ->from('municipality', 'municipality')
            ->where('municipality.center_latitude IS NOT NULL')
            ->andWhere('municipality.center_longitude IS NOT NULL')
            ->andWhere('municipality.insee_code IS NOT NULL')
            ->andWhere('municipality.center_latitude BETWEEN :minLatitude AND :maxLatitude')
            ->andWhere('municipality.center_longitude BETWEEN :minLongitude AND :maxLongitude')
            ->setParameter('minLatitude', $minLatitude)
            ->setParameter('maxLatitude', $maxLatitude)
            ->setParameter('minLongitude', $minLongitude)
            ->setParameter('maxLongitude', $maxLongitude)
            ->orderBy('municipality.name', 'ASC')
            ->addOrderBy('municipality.postal_code', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
        $choices = [];

        foreach ($rows as $row) {
            $latitude = (float) $row['center_latitude'];
            $longitude = (float) $row['center_longitude'];

            if (!$this->polygonContainsPoint($polygonPoints, $latitude, $longitude)) {
                continue;
            }

            $choices[] = [
                'value' => $row['insee_code'],
                'label' => $this->municipalityLabel($row['name'], $row['postal_code']),
            ];
        }

        return $choices;
    }

    /**
     * @param list<mixed> $inseeCodes
     *
     * @return list<string>
     */
    private function normalizeInseeCodes(array $inseeCodes): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $inseeCode): string => is_string($inseeCode) ? trim($inseeCode) : '',
                $inseeCodes,
            ),
            static fn (string $inseeCode): bool => '' !== $inseeCode,
        )));
    }

    private function municipalityLabel(string $name, ?string $postalCode): string
    {
        return null !== $postalCode && '' !== trim($postalCode)
            ? "{$postalCode} — {$name}"
            : $name;
    }

    /**
     * @param list<array{lat: mixed, lng: mixed}> $polygonPoints
     *
     * @return list<array{lat: float, lng: float}>
     */
    private function normalizePolygonPoints(array $polygonPoints): array
    {
        $normalizedPoints = [];

        foreach ($polygonPoints as $polygonPoint) {
            if (!is_array($polygonPoint)
                || !array_key_exists('lat', $polygonPoint)
                || !array_key_exists('lng', $polygonPoint)
                || !is_numeric($polygonPoint['lat'])
                || !is_numeric($polygonPoint['lng'])) {
                continue;
            }

            $normalizedPoints[] = [
                'lat' => (float) $polygonPoint['lat'],
                'lng' => (float) $polygonPoint['lng'],
            ];
        }

        return $normalizedPoints;
    }

    /**
     * @param list<array{lat: float, lng: float}> $polygonPoints
     */
    private function polygonContainsPoint(array $polygonPoints, float $latitude, float $longitude): bool
    {
        $containsPoint = false;
        $pointCount = count($polygonPoints);

        for ($pointIndex = 0, $previousPointIndex = $pointCount - 1; $pointIndex < $pointCount; $previousPointIndex = $pointIndex++) {
            $currentPoint = $polygonPoints[$pointIndex];
            $previousPoint = $polygonPoints[$previousPointIndex];
            $currentLatitude = $currentPoint['lat'];
            $currentLongitude = $currentPoint['lng'];
            $previousLatitude = $previousPoint['lat'];
            $previousLongitude = $previousPoint['lng'];

            $intersects = (($currentLatitude > $latitude) !== ($previousLatitude > $latitude))
                && ($longitude < ($previousLongitude - $currentLongitude) * ($latitude - $currentLatitude) / (($previousLatitude - $currentLatitude) ?: 0.0000001) + $currentLongitude);

            if ($intersects) {
                $containsPoint = !$containsPoint;
            }
        }

        return $containsPoint;
    }
}
