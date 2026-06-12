<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ContactDetailsEntity
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private DirectoryEntryEntity $directoryEntry;

    /**
     * @var Collection<int, EmailContactEntity>
     */
    private Collection $emailContacts;

    /**
     * @var Collection<int, PhoneContactEntity>
     */
    private Collection $phoneContacts;

    /**
     * @var Collection<int, AddressContactEntity>
     */
    private Collection $addressContacts;

    public function __construct()
    {
        $this->initializeUuid();
        $this->emailContacts = new ArrayCollection();
        $this->phoneContacts = new ArrayCollection();
        $this->addressContacts = new ArrayCollection();
    }

    public function __toString(): string
    {
        if (isset($this->directoryEntry)) {
            return (string) $this->directoryEntry;
        }

        return (string) $this->getUuid();
    }

    public function getDirectoryEntry(): ?DirectoryEntryEntity
    {
        return $this->directoryEntry ?? null;
    }

    public function setDirectoryEntry(DirectoryEntryEntity $directoryEntry): static
    {
        $this->directoryEntry = $directoryEntry;

        if ($directoryEntry->getContactDetails() !== $this) {
            $directoryEntry->setContactDetails($this);
        }

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
            $emailContactEntity->setContactDetails($this);
        }

        return $this;
    }

    public function removeEmailContact(EmailContactEntity $emailContactEntity): static
    {
        $this->emailContacts->removeElement($emailContactEntity);

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
            $phoneContactEntity->setContactDetails($this);
        }

        return $this;
    }

    public function removePhoneContact(PhoneContactEntity $phoneContactEntity): static
    {
        $this->phoneContacts->removeElement($phoneContactEntity);

        return $this;
    }

    /**
     * @return Collection<int, AddressContactEntity>
     */
    public function getAddressContacts(): Collection
    {
        return $this->addressContacts;
    }

    public function hasAddressContacts(): bool
    {
        return !$this->addressContacts->isEmpty();
    }

    public function addAddressContact(AddressContactEntity $addressContactEntity): static
    {
        if (!$this->addressContacts->contains($addressContactEntity)) {
            $this->addressContacts->add($addressContactEntity);
            $addressContactEntity->setContactDetails($this);
        }

        return $this;
    }

    public function removeAddressContact(AddressContactEntity $addressContactEntity): static
    {
        $this->addressContacts->removeElement($addressContactEntity);

        return $this;
    }
}
