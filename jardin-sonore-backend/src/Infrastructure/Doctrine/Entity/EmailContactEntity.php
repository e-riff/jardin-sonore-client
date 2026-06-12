<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\ContactDataSource;
use App\Domain\Model\AddressBook\EmailContactType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\NullableLabelTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;

class EmailContactEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use NullableLabelTrait;
    use UuidIdentifiableTrait;

    private ContactDetailsEntity $contactDetails;

    private string $emailAddress = '';

    private EmailContactType $type = EmailContactType::MAIN;

    private bool $optInNewsletter = true;

    private ContactDataSource $source = ContactDataSource::MANUAL;

    public function __construct()
    {
        $this->initializeUuid();
    }

    public function __toString(): string
    {
        return $this->emailAddress;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(string $emailAddress): static
    {
        $this->emailAddress = mb_strtolower(trim($emailAddress));

        return $this;
    }

    public function getContactDetails(): ?ContactDetailsEntity
    {
        return $this->contactDetails ?? null;
    }

    public function setContactDetails(ContactDetailsEntity $contactDetails): static
    {
        $this->contactDetails = $contactDetails;

        return $this;
    }

    public function getType(): EmailContactType
    {
        return $this->type;
    }

    public function setType(EmailContactType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function hasOptInNewsletter(): bool
    {
        return $this->optInNewsletter;
    }

    public function setOptInNewsletter(bool $optInNewsletter): static
    {
        $this->optInNewsletter = $optInNewsletter;

        return $this;
    }

    public function getSource(): ContactDataSource
    {
        return $this->source;
    }

    public function setSource(ContactDataSource $source): static
    {
        $this->source = $source;

        return $this;
    }
}
