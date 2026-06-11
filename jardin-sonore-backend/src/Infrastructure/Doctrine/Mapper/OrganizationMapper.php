<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\Organization;
use App\Domain\Model\ValueObject\PostalCode;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;

final readonly class OrganizationMapper
{
    public function __construct(private MunicipalityMapper $municipalityMapper)
    {
    }

    public function toDomain(OrganizationEntity $organizationEntity): Organization
    {
        $municipalityEntity = $organizationEntity->getMunicipality();

        return new Organization(
            name: $organizationEntity->getName(),
            type: $organizationEntity->getType(),
            sector: $organizationEntity->getSector(),
            customerStatus: $organizationEntity->getCustomerStatus(),
            address: $organizationEntity->getAddress(),
            postalCode: null !== $organizationEntity->getPostalCode() ? new PostalCode($organizationEntity->getPostalCode()) : null,
            city: $organizationEntity->getCity(),
            municipality: null !== $municipalityEntity ? $this->municipalityMapper->toDomain($municipalityEntity) : null,
            active: $organizationEntity->isActive(),
            uuid: $organizationEntity->getUuid(),
            id: $organizationEntity->getId(),
        );
    }

    public function toEntity(Organization $organization, ?OrganizationEntity $organizationEntity = null): OrganizationEntity
    {
        $organizationEntity ??= new OrganizationEntity();

        $municipality = $organization->getMunicipality();

        $organizationEntity
            ->setUuid($organization->getUuid())
            ->setName($organization->getName())
            ->setType($organization->getType())
            ->setSector($organization->getSector())
            ->setCustomerStatus($organization->getCustomerStatus())
            ->setAddress($organization->getAddress())
            ->setPostalCode($organization->getPostalCode()?->value())
            ->setCity($organization->getCity())
            ->setActive($organization->isActive())
            ->setMunicipality(null !== $municipality ? $this->municipalityMapper->toEntity($municipality, $organizationEntity->getMunicipality()) : null);

        return $organizationEntity;
    }
}
