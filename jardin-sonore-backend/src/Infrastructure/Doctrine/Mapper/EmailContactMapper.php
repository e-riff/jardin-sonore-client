<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\EmailContact;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;

final readonly class EmailContactMapper
{
    public function __construct(
        private OrganizationMapper $organizationMapper,
        private PersonMapper $personMapper,
    ) {
    }

    public function toDomain(EmailContactEntity $emailContactEntity): EmailContact
    {
        $organizationEntity = $emailContactEntity->getOrganization();
        $personEntity = $emailContactEntity->getPerson();

        return new EmailContact(
            emailAddress: new EmailAddress($emailContactEntity->getEmailAddress()),
            organization: null !== $organizationEntity ? $this->organizationMapper->toDomain($organizationEntity) : null,
            person: null !== $personEntity ? $this->personMapper->toDomain($personEntity) : null,
            label: $emailContactEntity->getLabel(),
            optInNewsletter: $emailContactEntity->hasOptInNewsletter(),
            active: $emailContactEntity->isActive(),
            source: $emailContactEntity->getSource(),
            uuid: $emailContactEntity->getUuid(),
            id: $emailContactEntity->getId(),
        );
    }

    public function toEntity(EmailContact $emailContact, ?EmailContactEntity $emailContactEntity = null): EmailContactEntity
    {
        $emailContactEntity ??= new EmailContactEntity();

        $organization = $emailContact->getOrganization();
        $person = $emailContact->getPerson();

        $emailContactEntity
            ->setUuid($emailContact->getUuid())
            ->setEmailAddress($emailContact->getEmailAddress()->value())
            ->setLabel($emailContact->getLabel())
            ->setOptInNewsletter($emailContact->hasNewsletterOptIn())
            ->setActive($emailContact->isActive())
            ->setSource($emailContact->getSource())
            ->setOrganization(null !== $organization ? $this->organizationMapper->toEntity($organization, $emailContactEntity->getOrganization()) : null)
            ->setPerson(null !== $person ? $this->personMapper->toEntity($person, $emailContactEntity->getPerson()) : null);

        return $emailContactEntity;
    }
}
