<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\Person;
use App\Infrastructure\Doctrine\Entity\PersonEntity;

final readonly class PersonMapper
{
    public function __construct(private OrganizationMapper $organizationMapper)
    {
    }

    public function toDomain(PersonEntity $personEntity): Person
    {
        $organizationEntity = $personEntity->getOrganization();

        if (null === $organizationEntity) {
            throw new \LogicException('Person entity must be attached to an organization.');
        }

        return new Person(
            firstName: $personEntity->getFirstName(),
            lastName: $personEntity->getLastName(),
            organization: $this->organizationMapper->toDomain($organizationEntity),
            role: $personEntity->getRole(),
            customerStatus: $personEntity->getCustomerStatus(),
            active: $personEntity->isActive(),
            uuid: $personEntity->getUuid(),
            id: $personEntity->getId(),
        );
    }

    public function toEntity(Person $person, ?PersonEntity $personEntity = null): PersonEntity
    {
        $personEntity ??= new PersonEntity();

        $personEntity
            ->setUuid($person->getUuid())
            ->setFirstName($person->getFirstName())
            ->setLastName($person->getLastName())
            ->setRole($person->getRole())
            ->setCustomerStatus($person->getCustomerStatus())
            ->setActive($person->isActive())
            ->setOrganization($this->organizationMapper->toEntity($person->getOrganization(), $personEntity->getOrganization()));

        return $personEntity;
    }
}
