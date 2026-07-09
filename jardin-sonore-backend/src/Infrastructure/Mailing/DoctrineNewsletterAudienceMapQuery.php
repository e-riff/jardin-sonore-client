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
}
