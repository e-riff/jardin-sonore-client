<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class PersonEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private string $firstName = '';

    private string $lastName = '';

    private ?string $role = null;

    private ?OrganizationEntity $organization = null;

    /**
     * @var Collection<int, EmailContactEntity>
     */
    private Collection $emailContacts;

    /**
     * @var Collection<int, PhoneContactEntity>
     */
    private Collection $phoneContacts;

    public function __construct()
    {
        $this->initializeUuid();
        $this->emailContacts = new ArrayCollection();
        $this->phoneContacts = new ArrayCollection();
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

    /**
     * @return Collection<int, EmailContactEntity>
     */
    public function getEmailContacts(): Collection
    {
        return $this->emailContacts;
    }

    public function hasEmailContacts(): bool
    {
        return !$this->emailContacts->isEmpty();
    }

    public function addEmailContact(EmailContactEntity $emailContactEntity): static
    {
        if (!$this->emailContacts->contains($emailContactEntity)) {
            $this->emailContacts->add($emailContactEntity);
            $emailContactEntity->setPerson($this);
        }

        return $this;
    }

    public function removeEmailContact(EmailContactEntity $emailContactEntity): static
    {
        if ($this->emailContacts->removeElement($emailContactEntity) && $emailContactEntity->getPerson() === $this) {
            $emailContactEntity->setPerson(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, PhoneContactEntity>
     */
    public function getPhoneContacts(): Collection
    {
        return $this->phoneContacts;
    }

    public function hasPhoneContacts(): bool
    {
        return !$this->phoneContacts->isEmpty();
    }

    public function addPhoneContact(PhoneContactEntity $phoneContactEntity): static
    {
        if (!$this->phoneContacts->contains($phoneContactEntity)) {
            $this->phoneContacts->add($phoneContactEntity);
            $phoneContactEntity->setPerson($this);
        }

        return $this;
    }

    public function removePhoneContact(PhoneContactEntity $phoneContactEntity): static
    {
        if ($this->phoneContacts->removeElement($phoneContactEntity) && $phoneContactEntity->getPerson() === $this) {
            $phoneContactEntity->setPerson(null);
        }

        return $this;
    }
}
