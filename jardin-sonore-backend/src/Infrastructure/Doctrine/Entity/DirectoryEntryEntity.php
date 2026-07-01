<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\DirectoryEntryType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

abstract class DirectoryEntryEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use TimestampableTrait;
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
        $this->initializeTimestamps();
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
}
