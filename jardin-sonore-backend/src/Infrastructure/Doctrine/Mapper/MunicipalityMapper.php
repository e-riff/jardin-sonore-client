<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Geo\Municipality;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Model\ValueObject\InseeCode;
use App\Domain\Model\ValueObject\PhoneNumber;
use App\Domain\Model\ValueObject\PostalCode;
use App\Domain\Model\ValueObject\Siren;
use App\Domain\Model\ValueObject\Siret;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;

final readonly class MunicipalityMapper
{
    public function __construct(private DepartmentMapper $departmentMapper)
    {
    }

    public function toDomain(MunicipalityEntity $municipalityEntity): Municipality
    {
        $departmentEntity = $municipalityEntity->getDepartment();

        if (null === $departmentEntity) {
            throw new \LogicException('Municipality entity must be attached to a department.');
        }

        return new Municipality(
            name: $municipalityEntity->getName(),
            department: $this->departmentMapper->toDomain($departmentEntity),
            phoneNumber: null !== $municipalityEntity->getPhoneNumber() ? new PhoneNumber($municipalityEntity->getPhoneNumber()) : null,
            emailAddress: null !== $municipalityEntity->getEmailAddress() ? new EmailAddress($municipalityEntity->getEmailAddress()) : null,
            address: $municipalityEntity->getAddress(),
            postalCode: null !== $municipalityEntity->getPostalCode() ? new PostalCode($municipalityEntity->getPostalCode()) : null,
            inseeCode: null !== $municipalityEntity->getInseeCode() ? new InseeCode($municipalityEntity->getInseeCode()) : null,
            siren: null !== $municipalityEntity->getSiren() ? new Siren($municipalityEntity->getSiren()) : null,
            siret: null !== $municipalityEntity->getSiret() ? new Siret($municipalityEntity->getSiret()) : null,
            geoShape: $municipalityEntity->getGeoShape(),
            uuid: $municipalityEntity->getUuid(),
            id: $municipalityEntity->getId(),
        );
    }

    public function toEntity(Municipality $municipality, ?MunicipalityEntity $municipalityEntity = null): MunicipalityEntity
    {
        $municipalityEntity ??= new MunicipalityEntity();

        $municipalityEntity
            ->setUuid($municipality->getUuid())
            ->setName($municipality->getName())
            ->setPhoneNumber($municipality->getPhoneNumber()?->value())
            ->setEmailAddress($municipality->getEmailAddress()?->value())
            ->setAddress($municipality->getAddress())
            ->setPostalCode($municipality->getPostalCode()?->value())
            ->setInseeCode($municipality->getInseeCode()?->value())
            ->setSiren($municipality->getSiren()?->value())
            ->setSiret($municipality->getSiret()?->value())
            ->setGeoShape($municipality->getGeoShape())
            ->setDepartment($this->departmentMapper->toEntity($municipality->getDepartment(), $municipalityEntity->getDepartment()));

        return $municipalityEntity;
    }
}
