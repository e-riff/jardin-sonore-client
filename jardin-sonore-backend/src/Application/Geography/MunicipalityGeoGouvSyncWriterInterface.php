<?php

declare(strict_types=1);

namespace App\Application\Geography;

interface MunicipalityGeoGouvSyncWriterInterface
{
    /**
     * @param array<string, mixed> $changes
     */
    public function applyChanges(int $municipalityId, array $changes): bool;

    public function flush(): void;

    public function clear(): void;
}
