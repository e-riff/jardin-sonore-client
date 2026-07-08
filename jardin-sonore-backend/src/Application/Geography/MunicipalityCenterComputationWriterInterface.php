<?php

declare(strict_types=1);

namespace App\Application\Geography;

interface MunicipalityCenterComputationWriterInterface
{
    public function updateCenterCoordinates(int $municipalityId, float $centerLatitude, float $centerLongitude): bool;
}
