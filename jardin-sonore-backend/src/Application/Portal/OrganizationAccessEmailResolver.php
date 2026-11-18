<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use InvalidArgumentException;

final class OrganizationAccessEmailResolver
{
    public function resolve(OrganizationEntity $organizationEntity, string $emailAddress, ?PersonEntity $preferredPersonEntity = null): OrganizationAccessEmailSelection
    {
        $emailAddress = mb_strtolower(trim($emailAddress));

        if (null !== $preferredPersonEntity) {
            if ($preferredPersonEntity->isActive() && $preferredPersonEntity->getOrganization() === $organizationEntity && $this->hasEmailAddress($preferredPersonEntity, $emailAddress)) {
                return new OrganizationAccessEmailSelection($emailAddress, $preferredPersonEntity);
            }

            throw new InvalidArgumentException('The selected email address is not linked to the organization.');
        }

        if ($this->hasEmailAddress($organizationEntity, $emailAddress)) {
            return new OrganizationAccessEmailSelection($emailAddress, null);
        }

        foreach ($organizationEntity->getPeople() as $personEntity) {
            if ($personEntity->isActive() && $this->hasEmailAddress($personEntity, $emailAddress)) {
                return new OrganizationAccessEmailSelection($emailAddress, $personEntity);
            }
        }

        throw new InvalidArgumentException('The selected email address is not linked to the organization.');
    }

    private function hasEmailAddress(OrganizationEntity|PersonEntity $directoryEntryEntity, string $emailAddress): bool
    {
        foreach ($directoryEntryEntity->getContactDetails()?->getEmailContactLinks() ?? [] as $emailContactLinkEntity) {
            $emailContactEntity = $emailContactLinkEntity->getEmailContact();

            if ($emailContactLinkEntity->isActive() && $emailContactEntity?->isActive() && mb_strtolower($emailContactEntity->getEmailAddress()) === $emailAddress) {
                return true;
            }
        }

        return false;
    }
}
