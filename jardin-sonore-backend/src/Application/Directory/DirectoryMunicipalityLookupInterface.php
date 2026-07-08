<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;

interface DirectoryMunicipalityLookupInterface
{
    public function findByNameAndPostalCode(?string $commune, ?string $postalCode): ?MunicipalityEntity;
}
