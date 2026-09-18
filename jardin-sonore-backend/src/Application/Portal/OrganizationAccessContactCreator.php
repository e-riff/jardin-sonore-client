<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;

final class OrganizationAccessContactCreator
{
    public function addOrganizationEmail(OrganizationEntity $organizationEntity, string $emailAddress, ?EmailContactEntity $emailContactEntity = null): void
    {
        $emailContactEntity ??= (new EmailContactEntity())->setEmailAddress($emailAddress);
        $emailContactLinkEntity = (new EmailContactLinkEntity())->setEmailContact($emailContactEntity);

        $organizationEntity->getContactDetails()?->addEmailContactLink($emailContactLinkEntity);
    }

    public function addPersonWithEmail(
        OrganizationEntity $organizationEntity,
        string $firstName,
        string $lastName,
        string $emailAddress,
        ?EmailContactEntity $emailContactEntity = null,
    ): PersonEntity {
        $personEntity = (new PersonEntity())
            ->setFirstName(trim($firstName))
            ->setLastName(trim($lastName));
        $organizationEntity->addPerson($personEntity);
        $this->addPersonEmail($personEntity, $emailAddress, $emailContactEntity);

        return $personEntity;
    }

    private function addPersonEmail(PersonEntity $personEntity, string $emailAddress, ?EmailContactEntity $emailContactEntity): void
    {
        $emailContactEntity ??= (new EmailContactEntity())->setEmailAddress($emailAddress);
        $emailContactLinkEntity = (new EmailContactLinkEntity())->setEmailContact($emailContactEntity);

        $personEntity->getContactDetails()?->addEmailContactLink($emailContactLinkEntity);
    }
}
