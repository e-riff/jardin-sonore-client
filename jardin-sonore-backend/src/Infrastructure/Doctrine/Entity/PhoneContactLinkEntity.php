<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\PhoneContactType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\NullableLabelTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;

class PhoneContactLinkEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use NullableLabelTrait;
    use TimestampableTrait;
    use UuidIdentifiableTrait;

    // transient null allowed for orphan removal
    // @phpstan-ignore-next-line
    private ?ContactDetailsEntity $contactDetails = null;

    // transient null allowed during rebinding/removal
    // @phpstan-ignore-next-line
    private ?PhoneContactEntity $phoneContact = null;

    private PhoneContactType $type = PhoneContactType::MAIN;

    private bool $pendingRemoval = false;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->setActive(true);
    }

    public function __toString(): string
    {
        return (string) $this->getPhoneNumber();
    }

    public function getContactDetails(): ?ContactDetailsEntity
    {
        return $this->contactDetails ?? null;
    }

    public function setContactDetails(?ContactDetailsEntity $contactDetails): static
    {
        $this->contactDetails = $contactDetails;

        return $this;
    }

    public function getPhoneContact(): ?PhoneContactEntity
    {
        return $this->phoneContact ?? null;
    }

    public function setPhoneContact(?PhoneContactEntity $phoneContact): static
    {
        $this->phoneContact = $phoneContact;

        if ($phoneContact instanceof PhoneContactEntity && !$phoneContact->getPhoneContactLinks()->contains($this)) {
            $phoneContact->addPhoneContactLink($this);
        }

        return $this;
    }

    public function getType(): PhoneContactType
    {
        return $this->type;
    }

    public function setType(?PhoneContactType $type): static
    {
        if (null === $type) {
            $this->pendingRemoval = true;

            return $this;
        }

        $this->type = $type;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->getPhoneContact()?->getPhoneNumber();
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        if (null === $phoneNumber || '' === trim($phoneNumber)) {
            $this->pendingRemoval = true;

            return $this;
        }

        if (!$this->getPhoneContact() instanceof PhoneContactEntity) {
            $this->setPhoneContact(new PhoneContactEntity());
        }

        $this->phoneContact->setPhoneNumber($phoneNumber);

        return $this;
    }

    public function isPendingRemoval(): bool
    {
        return $this->pendingRemoval;
    }
}
