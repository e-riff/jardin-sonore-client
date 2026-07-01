<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\ValueObject\PhoneNumber;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class PhoneContactEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use TimestampableTrait;
    use UuidIdentifiableTrait;

    private string $phoneNumber = '';

    /**
     * @var Collection<int, PhoneContactLinkEntity>
     */
    private Collection $phoneContactLinks;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->setActive(true);
        $this->phoneContactLinks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->phoneNumber;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = (new PhoneNumber($phoneNumber))->value();

        return $this;
    }

    /**
     * @return Collection<int, PhoneContactLinkEntity>
     */
    public function getPhoneContactLinks(): Collection
    {
        return $this->phoneContactLinks;
    }

    public function addPhoneContactLink(PhoneContactLinkEntity $phoneContactLink): static
    {
        if (!$this->phoneContactLinks->contains($phoneContactLink)) {
            $this->phoneContactLinks->add($phoneContactLink);

            if ($phoneContactLink->getPhoneContact() !== $this) {
                $phoneContactLink->setPhoneContact($this);
            }
        }

        return $this;
    }

    public function removePhoneContactLink(PhoneContactLinkEntity $phoneContactLink): static
    {
        $this->phoneContactLinks->removeElement($phoneContactLink);

        return $this;
    }

    public function getLinkedDirectoryEntriesSummary(): string
    {
        $directoryEntries = $this->phoneContactLinks->map(
            static fn (PhoneContactLinkEntity $phoneContactLink): string => (string) ($phoneContactLink->getContactDetails()?->getDirectoryEntry() ?? ''),
        )->toArray();
        $directoryEntries = array_values(array_unique(array_filter(array_map('trim', $directoryEntries))));

        return [] === $directoryEntries ? '—' : implode("\n", $directoryEntries);
    }
}
