<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectoryMunicipalityLookupInterface;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Repository\MunicipalityEntityRepository;

final readonly class DoctrineDirectoryMunicipalityLookup implements DirectoryMunicipalityLookupInterface
{
    public function __construct(
        private MunicipalityEntityRepository $municipalityEntityRepository,
    ) {
    }

    public function findByNameAndPostalCode(?string $commune, ?string $postalCode): ?MunicipalityEntity
    {
        if (null === $commune) {
            return null;
        }

        return $this->municipalityEntityRepository->findOneByNameAndPostalCode($commune, $postalCode);
    }
}
