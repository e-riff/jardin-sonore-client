<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\DirectoryEntryType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

abstract class DirectoryEntryEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private DirectoryEntryType $entryType;

    private ?CustomerStatus $customerStatus = null;

    private ?ContactDetailsEntity $contactDetails = null;

    /**
     * @var Collection<int, TagEntity>
     */
    private Collection $tags;

    protected function __construct(DirectoryEntryType $entryType)
    {
        $this->initializeUuid();
        $this->entryType = $entryType;
        $this->tags = new ArrayCollection();
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

    public function getCustomerStatus(): ?CustomerStatus
    {
        return $this->customerStatus;
    }

    public function setCustomerStatus(?CustomerStatus $customerStatus): static
    {
        $this->customerStatus = $customerStatus;

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

    /**
     * @return Collection<int, TagEntity>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(TagEntity $tagEntity): static
    {
        if (!$this->tags->contains($tagEntity)) {
            $this->tags->add($tagEntity);
            $tagEntity->addDirectoryEntry($this);
        }

        return $this;
    }

    public function removeTag(TagEntity $tagEntity): static
    {
        if ($this->tags->removeElement($tagEntity)) {
            $tagEntity->removeDirectoryEntry($this);
        }

        return $this;
    }

    public function getEmailContactsSummary(): string
    {
        return $this->summarizeContacts(
            $this->contactDetails?->getEmailContacts()->map(static fn (EmailContactEntity $emailContactEntity): string => (string) $emailContactEntity)->toArray() ?? [],
        );
    }

    public function getPhoneContactsSummary(): string
    {
        return $this->summarizeContacts(
            $this->contactDetails?->getPhoneContacts()->map(static fn (PhoneContactEntity $phoneContactEntity): string => (string) $phoneContactEntity)->toArray() ?? [],
        );
    }

    public function getAddressContactsSummary(): string
    {
        return $this->summarizeContacts(
            $this->contactDetails?->getAddressContacts()->map(static fn (AddressContactEntity $addressContactEntity): string => (string) $addressContactEntity)->toArray() ?? [],
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
