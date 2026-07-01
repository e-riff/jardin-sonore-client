<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ContactDetailsEntity
{
    use IdentifiableTrait;
    use TimestampableTrait;
    use UuidIdentifiableTrait;

    private DirectoryEntryEntity $directoryEntry;

    /**
     * @var Collection<int, EmailContactLinkEntity>
     */
    private Collection $emailContactLinks;

    /**
     * @var Collection<int, PhoneContactLinkEntity>
     */
    private Collection $phoneContactLinks;

    /**
     * @var Collection<int, AddressContactEntity>
     */
    private Collection $addressContacts;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->emailContactLinks = new ArrayCollection();
        $this->phoneContactLinks = new ArrayCollection();
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
     * @return Collection<int, EmailContactLinkEntity>
     */
    public function getEmailContactLinks(): Collection
    {
        return $this->emailContactLinks;
    }

    public function hasEmailContacts(): bool
    {
        return !$this->emailContactLinks->isEmpty();
    }

    public function addEmailContactLink(EmailContactLinkEntity $emailContactLink): static
    {
        if (!$this->emailContactLinks->contains($emailContactLink)) {
            $this->emailContactLinks->add($emailContactLink);
            $emailContactLink->setContactDetails($this);
        }

        return $this;
    }

    public function removeEmailContactLink(EmailContactLinkEntity $emailContactLink): static
    {
        if ($this->emailContactLinks->removeElement($emailContactLink)) {
            $emailContactLink->getEmailContact()?->removeEmailContactLink($emailContactLink);

            if ($emailContactLink->getContactDetails() === $this) {
                $emailContactLink->setContactDetails(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PhoneContactLinkEntity>
     */
    public function getPhoneContactLinks(): Collection
    {
        return $this->phoneContactLinks;
    }

    public function hasPhoneContacts(): bool
    {
        return !$this->phoneContactLinks->isEmpty();
    }

    public function addPhoneContactLink(PhoneContactLinkEntity $phoneContactLink): static
    {
        if (!$this->phoneContactLinks->contains($phoneContactLink)) {
            $this->phoneContactLinks->add($phoneContactLink);
            $phoneContactLink->setContactDetails($this);
        }

        return $this;
    }

    public function removePhoneContactLink(PhoneContactLinkEntity $phoneContactLink): static
    {
        if ($this->phoneContactLinks->removeElement($phoneContactLink)) {
            $phoneContactLink->getPhoneContact()?->removePhoneContactLink($phoneContactLink);

            if ($phoneContactLink->getContactDetails() === $this) {
                $phoneContactLink->setContactDetails(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, EmailContactEntity>
     */
    public function getEmailContacts(): Collection
    {
        return new ArrayCollection($this->emailContactLinks->map(
            static fn (EmailContactLinkEntity $emailContactLink): ?EmailContactEntity => $emailContactLink->getEmailContact(),
        )->filter(static fn (?EmailContactEntity $emailContactEntity): bool => $emailContactEntity instanceof EmailContactEntity)->toArray());
    }

    /**
     * @return Collection<int, PhoneContactEntity>
     */
    public function getPhoneContacts(): Collection
    {
        return new ArrayCollection($this->phoneContactLinks->map(
            static fn (PhoneContactLinkEntity $phoneContactLink): ?PhoneContactEntity => $phoneContactLink->getPhoneContact(),
        )->filter(static fn (?PhoneContactEntity $phoneContactEntity): bool => $phoneContactEntity instanceof PhoneContactEntity)->toArray());
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
        if ($this->addressContacts->removeElement($addressContactEntity) && $addressContactEntity->getContactDetails() === $this) {
            $addressContactEntity->setContactDetails(null);
        }

        return $this;
    }

    public function getEmailContactsSummary(): string
    {
        return $this->summarizeContacts(
            $this->emailContactLinks->map(static fn (EmailContactLinkEntity $emailContactLink): string => (string) $emailContactLink)->toArray(),
        );
    }

    public function getPhoneContactsSummary(): string
    {
        return $this->summarizeContacts(
            $this->phoneContactLinks->map(static fn (PhoneContactLinkEntity $phoneContactLink): string => (string) $phoneContactLink)->toArray(),
        );
    }

    public function getAddressContactsSummary(): string
    {
        return $this->summarizeContacts(
            $this->addressContacts->map(static fn (AddressContactEntity $addressContactEntity): string => (string) $addressContactEntity)->toArray(),
        );
    }

    /**
     * @param list<string> $contacts
     */
    private function summarizeContacts(array $contacts): string
    {
        $contacts = array_values(array_filter(array_map('trim', $contacts)));

        return [] === $contacts ? '—' : implode("\n", $contacts);
    }
}
