<?php

declare(strict_types=1);

namespace App\Application\Geography;

final readonly class AddressMunicipalityCandidate
{
    public function __construct(
        public int $municipalityId,
        public string $name,
    ) {
    }
}
