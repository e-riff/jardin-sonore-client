<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\DirectoryEntryType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;

abstract class DirectoryEntryEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use TimestampableTrait;
    use UuidIdentifiableTrait;

    private DirectoryEntryType $entryType;

    private ?ContactDetailsEntity $contactDetails = null;

    protected function __construct(DirectoryEntryType $entryType)
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->entryType = $entryType;
        $this->setContactDetails(new ContactDetailsEntity());
    }

    public function getEntryType(): DirectoryEntryType
    {
        return $this->entryType;
    }

    public function setEntryType(DirectoryEntryType $entryType): static
    {
        $this->entryType = $entryType;

        return $this;
    }

    abstract public function __toString(): string;

    public function getContactDetails(): ?ContactDetailsEntity
    {
        return $this->contactDetails;
    }

    public function setContactDetails(ContactDetailsEntity $contactDetails): static
    {
        $this->contactDetails = $contactDetails;

        if ($contactDetails->getDirectoryEntry() !== $this) {
            $contactDetails->setDirectoryEntry($this);
        }

        return $this;
    }

    public function getEmailContactsSummary(): string
    {
        return $this->contactDetails?->getEmailContactsSummary() ?? '—';
    }

    public function getPhoneContactsSummary(): string
    {
        return $this->contactDetails?->getPhoneContactsSummary() ?? '—';
    }

    public function getAddressContactsSummary(): string
    {
        return $this->contactDetails?->getAddressContactsSummary() ?? '—';
    }

    public function getMunicipalitySummary(): string
    {
        foreach ($this->contactDetails?->getAddressContacts() ?? [] as $addressContactEntity) {
            if (!$addressContactEntity->isActive()) {
                continue;
            }
            $municipalityEntity = $addressContactEntity->getMunicipality();

            if (null !== $municipalityEntity) {
                $postalCode = $municipalityEntity->getPostalCode();
                $label = null !== $postalCode && '' !== $postalCode
                    ? "{$postalCode} — {$municipalityEntity->getName()}"
                    : $municipalityEntity->getName();

                return $label;
            }
        }

        return '—';
    }

    public function getDepartmentSummary(): string
    {
        foreach ($this->contactDetails?->getAddressContacts() ?? [] as $addressContactEntity) {
            $departmentEntity = $addressContactEntity->getMunicipality()?->getDepartment();

            if (null === $departmentEntity) {
                continue;
            }

            return "{$departmentEntity->getCode()} — {$departmentEntity->getName()}";
        }

        return '—';
    }
}
