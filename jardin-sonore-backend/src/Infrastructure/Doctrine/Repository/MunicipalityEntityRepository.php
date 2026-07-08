<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MunicipalityEntity>
 */
final class MunicipalityEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, MunicipalityEntity::class);
    }

    public function findOneByNameAndPostalCode(string $name, ?string $postalCode): ?MunicipalityEntity
    {
        $criteria = ['name' => $name];

        if (null !== $postalCode) {
            $criteria['postalCode'] = $postalCode;
        }

        $municipality = $this->findOneBy($criteria);

        return $municipality instanceof MunicipalityEntity ? $municipality : null;
    }
}
