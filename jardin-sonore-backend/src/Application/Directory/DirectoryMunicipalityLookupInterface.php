<?php

declare(strict_types=1);

namespace App\Application\Directory;

interface DirectoryMunicipalityLookupInterface
{
    public function findIdByNameAndPostalCode(?string $commune, ?string $postalCode): ?int;
}
