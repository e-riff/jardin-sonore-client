<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Geo\Municipality;
use App\Domain\Model\ValueObject\InseeCode;
use App\Domain\Repository\MunicipalityRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Mapper\MunicipalityMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class MunicipalityDoctrineRepository implements MunicipalityRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MunicipalityMapper $municipalityMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?Municipality
    {
        $municipalityEntity = $this->entityManager->getRepository(MunicipalityEntity::class)->findOneBy(['uuid' => $uuid]);

        return $municipalityEntity instanceof MunicipalityEntity ? $this->municipalityMapper->toDomain($municipalityEntity) : null;
    }

    public function findByInseeCode(InseeCode $inseeCode): ?Municipality
    {
        $municipalityEntity = $this->entityManager->getRepository(MunicipalityEntity::class)->findOneBy(['inseeCode' => $inseeCode->value()]);

        return $municipalityEntity instanceof MunicipalityEntity ? $this->municipalityMapper->toDomain($municipalityEntity) : null;
    }

    public function save(Municipality $municipality): void
    {
        $municipalityEntity = $this->entityManager->getRepository(MunicipalityEntity::class)->findOneBy(['uuid' => $municipality->getUuid()]);

        $this->entityManager->persist($this->municipalityMapper->toEntity(
            municipality: $municipality,
            municipalityEntity: $municipalityEntity instanceof MunicipalityEntity ? $municipalityEntity : null,
        ));
        $this->entityManager->flush();
    }
}
