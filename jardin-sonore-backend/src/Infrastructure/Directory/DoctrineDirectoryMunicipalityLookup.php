<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectoryMunicipalityLookupInterface;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineDirectoryMunicipalityLookup implements DirectoryMunicipalityLookupInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findByNameAndPostalCode(?string $commune, ?string $postalCode): ?MunicipalityEntity
    {
        if (null === $commune) {
            return null;
        }

        $criteria = ['name' => $commune];

        if (null !== $postalCode) {
            $criteria['postalCode'] = $postalCode;
        }

        $municipality = $this->entityManager->getRepository(MunicipalityEntity::class)->findOneBy($criteria);

        return $municipality instanceof MunicipalityEntity ? $municipality : null;
    }
}
