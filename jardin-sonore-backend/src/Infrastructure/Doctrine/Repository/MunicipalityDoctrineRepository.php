<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Geo\Municipality;
use App\Domain\Model\ValueObject\InseeCode;
use App\Domain\Repository\MunicipalityRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Mapper\MunicipalityMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<MunicipalityEntity>
 */
final class MunicipalityDoctrineRepository extends ServiceEntityRepository implements MunicipalityRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly MunicipalityMapper $municipalityMapper,
    ) {
        parent::__construct($managerRegistry, MunicipalityEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?Municipality
    {
        $municipalityEntity = $this->findOneBy(['uuid' => $uuid]);

        return $municipalityEntity instanceof MunicipalityEntity ? $this->municipalityMapper->toDomain($municipalityEntity) : null;
    }

    public function findByInseeCode(InseeCode $inseeCode): ?Municipality
    {
        $municipalityEntity = $this->findOneBy(['inseeCode' => $inseeCode->value()]);

        return $municipalityEntity instanceof MunicipalityEntity ? $this->municipalityMapper->toDomain($municipalityEntity) : null;
    }

    public function findEntityByNameAndPostalCode(string $name, ?string $postalCode): ?MunicipalityEntity
    {
        $criteria = ['name' => $name];

        if (null !== $postalCode) {
            $criteria['postalCode'] = $postalCode;
        }

        $municipalityEntity = $this->findOneBy($criteria);

        return $municipalityEntity instanceof MunicipalityEntity ? $municipalityEntity : null;
    }

    public function save(Municipality $municipality): void
    {
        $municipalityEntity = $this->findOneBy(['uuid' => $municipality->getUuid()]);

        $this->getEntityManager()->persist($this->municipalityMapper->toEntity(
            municipality: $municipality,
            municipalityEntity: $municipalityEntity instanceof MunicipalityEntity ? $municipalityEntity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
