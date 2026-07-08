<?php

declare(strict_types=1);

namespace App\Application\Geography;

interface MunicipalityCenterComputationReaderInterface
{
    /**
     * @return iterable<int, MunicipalityCenterSnapshot>
     */
    public function iterateMunicipalityCenterSnapshots(bool $force, int $batchSize): iterable;
}
