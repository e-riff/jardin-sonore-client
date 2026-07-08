<?php

declare(strict_types=1);

namespace App\Infrastructure\Geography;

use App\Application\Geography\MunicipalityGeoGouvSyncReaderInterface;
use App\Application\Geography\MunicipalitySyncSnapshot;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMunicipalityGeoGouvSyncReader implements MunicipalityGeoGouvSyncReaderInterface
{
    private const int READ_BATCH_SIZE = 100;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function iterateMunicipalitySnapshots(string $inseeCode, int $offset, ?int $limit): iterable
    {
        $connection = $this->entityManager->getConnection();

        if ('' !== $inseeCode) {
            $municipalityRow = $connection->createQueryBuilder()
                ->select(
                    'municipality.id',
                    'municipality.name',
                    'municipality.postal_code',
                    'municipality.insee_code',
                    'municipality.siren',
                    'municipality.center_latitude',
                    'municipality.center_longitude',
                )
                ->from('municipality', 'municipality')
                ->andWhere('municipality.insee_code = :inseeCode')
                ->setParameter('inseeCode', $inseeCode)
                ->executeQuery()
                ->fetchAssociative();

            if (is_array($municipalityRow)) {
                yield $this->mapSnapshot($municipalityRow);
            }

            return;
        }

        $remaining = $limit;
        $skipped = 0;
        $lastId = 0;

        while (true) {
            $batchSize = null !== $remaining
                ? min(self::READ_BATCH_SIZE, $remaining + max(0, $offset - $skipped))
                : self::READ_BATCH_SIZE;

            /** @var list<array<string, mixed>> $rows */
            $rows = $connection->createQueryBuilder()
                ->select(
                    'municipality.id',
                    'municipality.name',
                    'municipality.postal_code',
                    'municipality.insee_code',
                    'municipality.siren',
                    'municipality.center_latitude',
                    'municipality.center_longitude',
                )
                ->from('municipality', 'municipality')
                ->andWhere('municipality.id > :lastId')
                ->setParameter('lastId', $lastId)
                ->orderBy('municipality.id', 'ASC')
                ->setMaxResults($batchSize)
                ->executeQuery()
                ->fetchAllAssociative();

            if ([] === $rows) {
                return;
            }

            foreach ($rows as $municipalityRow) {
                $lastId = (int) $municipalityRow['id'];

                if ($skipped < $offset) {
                    ++$skipped;
                    continue;
                }

                if (null !== $remaining && 0 >= $remaining) {
                    return;
                }

                if (null !== $remaining) {
                    --$remaining;
                }

                yield $this->mapSnapshot($municipalityRow);
            }

            if (null !== $remaining && 0 >= $remaining) {
                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $municipalityRow
     */
    private function mapSnapshot(array $municipalityRow): MunicipalitySyncSnapshot
    {
        return new MunicipalitySyncSnapshot(
            id: (int) $municipalityRow['id'],
            name: (string) $municipalityRow['name'],
            postalCode: is_string($municipalityRow['postal_code']) ? $municipalityRow['postal_code'] : null,
            inseeCode: is_string($municipalityRow['insee_code']) ? $municipalityRow['insee_code'] : null,
            siren: is_string($municipalityRow['siren']) ? $municipalityRow['siren'] : null,
            centerLatitude: is_numeric($municipalityRow['center_latitude']) ? (float) $municipalityRow['center_latitude'] : null,
            centerLongitude: is_numeric($municipalityRow['center_longitude']) ? (float) $municipalityRow['center_longitude'] : null,
        );
    }
}
