<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectoryMunicipalityLookupInterface;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Repository\MunicipalityDoctrineRepository;

final readonly class DoctrineDirectoryMunicipalityLookup implements DirectoryMunicipalityLookupInterface
{
    public function __construct(
        private MunicipalityDoctrineRepository $municipalityDoctrineRepository,
    ) {
    }

    public function findIdByNameAndPostalCode(?string $commune, ?string $postalCode): ?int
    {
        if (null === $commune) {
            return null;
        }

        $criteria = ['name' => $commune];

        if (null !== $postalCode) {
            $criteria['postalCode'] = $postalCode;
        }

        $municipality = $this->municipalityDoctrineRepository->findEntityByNameAndPostalCode(
            $criteria['name'],
            $criteria['postalCode'] ?? null,
        );

        if (!$municipality instanceof MunicipalityEntity && null !== $postalCode) {
            $nameMatches = $this->municipalityDoctrineRepository->findBy(['name' => $commune], limit: 2);
            $municipality = 1 === count($nameMatches) ? $nameMatches[0] : null;
        }

        return $municipality instanceof MunicipalityEntity ? $municipality->getId() : null;
    }
}
