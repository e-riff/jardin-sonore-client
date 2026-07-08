<?php

declare(strict_types=1);

namespace App\Infrastructure\Geography;

use App\Application\Geography\MunicipalityCenterComputationReaderInterface;
use App\Application\Geography\MunicipalityCenterSnapshot;
use Doctrine\DBAL\Connection;

final readonly class DoctrineMunicipalityCenterComputationReader implements MunicipalityCenterComputationReaderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function iterateMunicipalityCenterSnapshots(bool $force, int $batchSize): iterable
    {
        $lastProcessedId = 0;

        do {
            $queryBuilder = $this->connection->createQueryBuilder()
                ->select('municipality.id', 'municipality.geo_shape')
                ->from('municipality', 'municipality')
                ->andWhere('municipality.id > :lastProcessedId')
                ->andWhere('municipality.geo_shape IS NOT NULL')
                ->setParameter('lastProcessedId', $lastProcessedId)
                ->orderBy('municipality.id', 'ASC')
                ->setMaxResults($batchSize);

            if (false === $force) {
                $queryBuilder
                    ->andWhere('municipality.center_latitude IS NULL')
                    ->andWhere('municipality.center_longitude IS NULL');
            }

            /** @var list<array{id:int|string, geo_shape:?string}> $rows */
            $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

            foreach ($rows as $row) {
                $lastProcessedId = (int) $row['id'];

                yield new MunicipalityCenterSnapshot(
                    id: $lastProcessedId,
                    geoShape: is_string($row['geo_shape']) ? $row['geo_shape'] : null,
                );
            }
        } while ([] !== $rows);
    }
}
