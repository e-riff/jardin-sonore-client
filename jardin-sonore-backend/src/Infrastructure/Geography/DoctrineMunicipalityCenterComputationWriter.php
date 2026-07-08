<?php

declare(strict_types=1);

namespace App\Infrastructure\Geography;

use App\Application\Geography\MunicipalityCenterComputationWriterInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMunicipalityCenterComputationWriter implements MunicipalityCenterComputationWriterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function updateCenterCoordinates(int $municipalityId, float $centerLatitude, float $centerLongitude): bool
    {
        return 0 < $this->entityManager->getConnection()->executeStatement(
            'UPDATE municipality SET center_latitude = :centerLatitude, center_longitude = :centerLongitude WHERE id = :id',
            [
                'centerLatitude' => $centerLatitude,
                'centerLongitude' => $centerLongitude,
                'id' => $municipalityId,
            ],
        );
    }
}
