<?php

declare(strict_types=1);

namespace App\Application\Mailing;

final readonly class AudienceMapMunicipalityShape
{
    /**
     * @param array<string, mixed>|list<mixed> $geoShape
     */
    public function __construct(
        public string $inseeCode,
        public string $label,
        public array $geoShape,
    ) {
    }
}
