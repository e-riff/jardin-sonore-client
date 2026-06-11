<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\PhoneContact;
use App\Domain\Model\ValueObject\PhoneNumber;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;

final readonly class PhoneContactMapper
{
    public function __construct(
        private OrganizationMapper $organizationMapper,
        private PersonMapper $personMapper,
    ) {
    }

    public function toDomain(PhoneContactEntity $phoneContactEntity): PhoneContact
    {
        $organizationEntity = $phoneContactEntity->getOrganization();
        $personEntity = $phoneContactEntity->getPerson();

        return new PhoneContact(
            phoneNumber: new PhoneNumber($phoneContactEntity->getPhoneNumber()),
            organization: null !== $organizationEntity ? $this->organizationMapper->toDomain($organizationEntity) : null,
            person: null !== $personEntity ? $this->personMapper->toDomain($personEntity) : null,
            label: $phoneContactEntity->getLabel(),
            active: $phoneContactEntity->isActive(),
            uuid: $phoneContactEntity->getUuid(),
            id: $phoneContactEntity->getId(),
        );
    }

    public function toEntity(PhoneContact $phoneContact, ?PhoneContactEntity $phoneContactEntity = null): PhoneContactEntity
    {
        $phoneContactEntity ??= new PhoneContactEntity();

        $organization = $phoneContact->getOrganization();
        $person = $phoneContact->getPerson();

        $phoneContactEntity
            ->setUuid($phoneContact->getUuid())
            ->setPhoneNumber($phoneContact->getPhoneNumber()->value())
            ->setLabel($phoneContact->getLabel())
            ->setActive($phoneContact->isActive())
            ->setOrganization(null !== $organization ? $this->organizationMapper->toEntity($organization, $phoneContactEntity->getOrganization()) : null)
            ->setPerson(null !== $person ? $this->personMapper->toEntity($person, $phoneContactEntity->getPerson()) : null);

        return $phoneContactEntity;
    }
}
