<?php

declare(strict_types=1);

namespace App\Application\Geography;

interface MunicipalityGeoGouvSyncReaderInterface
{
    /**
     * @return iterable<int, MunicipalitySyncSnapshot>
     */
    public function iterateMunicipalitySnapshots(string $inseeCode, int $offset, ?int $limit): iterable;
}
