<?php

declare(strict_types=1);

namespace App\Application\Geography;

final readonly class MunicipalitySyncSnapshot
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $postalCode,
        public ?string $inseeCode,
        public ?string $siren,
        public ?float $centerLatitude,
        public ?float $centerLongitude,
    ) {
    }
}
