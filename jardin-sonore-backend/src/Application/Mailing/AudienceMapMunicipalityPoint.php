<?php

declare(strict_types=1);

namespace App\Application\Mailing;

final readonly class AudienceMapMunicipalityPoint
{
    public function __construct(
        public string $inseeCode,
        public string $label,
        public float $latitude,
        public float $longitude,
    ) {
    }
}
