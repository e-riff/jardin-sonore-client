<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\DirectoryEntryType;

class PersonEntity extends DirectoryEntryEntity
{
    private string $firstName = '';

    private string $lastName = '';

    private ?string $role = null;

    private ?OrganizationEntity $organization = null;

    public function __construct()
    {
        parent::__construct(DirectoryEntryType::PERSON);
    }

    public function __toString(): string
    {
        return trim(sprintf('%s %s', $this->firstName, $this->lastName));
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getOrganization(): ?OrganizationEntity
    {
        return $this->organization;
    }

    public function setOrganization(?OrganizationEntity $organization): static
    {
        $this->organization = $organization;

        return $this;
    }
}
