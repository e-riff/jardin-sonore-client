<?php

declare(strict_types=1);

namespace App\Infrastructure\Geography;

use App\Application\Geography\MunicipalityGeoGouvSyncWriterInterface;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Repository\MunicipalityEntityRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMunicipalityGeoGouvSyncWriter implements MunicipalityGeoGouvSyncWriterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MunicipalityEntityRepository $municipalityEntityRepository,
    ) {
    }

    public function applyChanges(int $municipalityId, array $changes): bool
    {
        $municipality = $this->municipalityEntityRepository->find($municipalityId);

        if (!$municipality instanceof MunicipalityEntity) {
            return false;
        }

        if (array_key_exists('nameValue', $changes)) {
            $municipality->setName($changes['nameValue']);
        }

        if (array_key_exists('postalCodeValue', $changes)) {
            $municipality->setPostalCode($changes['postalCodeValue']);
        }

        if (array_key_exists('sirenValue', $changes)) {
            $municipality->setSiren($changes['sirenValue']);
        }

        if (array_key_exists('centerLatitudeValue', $changes) || array_key_exists('centerLongitudeValue', $changes)) {
            $municipality
                ->setCenterLatitude($changes['centerLatitudeValue'] ?? null)
                ->setCenterLongitude($changes['centerLongitudeValue'] ?? null);
        }

        $this->entityManager->persist($municipality);

        return true;
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function clear(): void
    {
        $this->entityManager->clear();
    }
}
