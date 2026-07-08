<?php

declare(strict_types=1);

namespace App\Application\Geography;

final readonly class MunicipalityCenterSnapshot
{
    public function __construct(
        public int $id,
        public ?string $geoShape,
    ) {
    }
}
