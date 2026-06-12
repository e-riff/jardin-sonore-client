<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\Organization;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;

final readonly class OrganizationMapper
{
    public function toDomain(OrganizationEntity $organizationEntity): Organization
    {
        return new Organization(
            name: $organizationEntity->getName(),
            type: $organizationEntity->getType(),
            sector: $organizationEntity->getSector(),
            customerStatus: $organizationEntity->getCustomerStatus(),
            active: $organizationEntity->isActive(),
            uuid: $organizationEntity->getUuid(),
            id: $organizationEntity->getId(),
        );
    }

    public function toEntity(Organization $organization, ?OrganizationEntity $organizationEntity = null): OrganizationEntity
    {
        $organizationEntity ??= new OrganizationEntity();

        $organizationEntity
            ->setUuid($organization->getUuid())
            ->setName($organization->getName())
            ->setType($organization->getType())
            ->setSector($organization->getSector())
            ->setCustomerStatus($organization->getCustomerStatus())
            ->setActive($organization->isActive());

        return $organizationEntity;
    }
}
