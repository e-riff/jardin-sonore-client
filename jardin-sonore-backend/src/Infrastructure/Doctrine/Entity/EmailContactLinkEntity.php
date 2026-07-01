<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\ContactDataSource;
use App\Domain\Model\AddressBook\EmailContactType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\NullableLabelTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;

class EmailContactLinkEntity
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
    private ?EmailContactEntity $emailContact = null;

    private EmailContactType $type = EmailContactType::MAIN;

    private bool $pendingRemoval = false;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->setActive(true);
    }

    public function __toString(): string
    {
        return (string) $this->getEmailAddress();
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

    public function getEmailContact(): ?EmailContactEntity
    {
        return $this->emailContact ?? null;
    }

    public function setEmailContact(?EmailContactEntity $emailContact): static
    {
        $this->emailContact = $emailContact;

        if ($emailContact instanceof EmailContactEntity && !$emailContact->getEmailContactLinks()->contains($this)) {
            $emailContact->addEmailContactLink($this);
        }

        return $this;
    }

    public function getType(): EmailContactType
    {
        return $this->type;
    }

    public function setType(?EmailContactType $type): static
    {
        if (null === $type) {
            $this->pendingRemoval = true;

            return $this;
        }

        $this->type = $type;

        return $this;
    }

    public function getEmailAddress(): ?string
    {
        return $this->getEmailContact()?->getEmailAddress();
    }

    public function setEmailAddress(?string $emailAddress): static
    {
        if (null === $emailAddress || '' === trim($emailAddress)) {
            $this->pendingRemoval = true;

            return $this;
        }

        if (!$this->getEmailContact() instanceof EmailContactEntity) {
            $this->setEmailContact(new EmailContactEntity());
        }

        $this->emailContact->setEmailAddress($emailAddress);

        return $this;
    }

    public function isPendingRemoval(): bool
    {
        return $this->pendingRemoval;
    }

    public function getSource(): ?ContactDataSource
    {
        return $this->getEmailContact()?->getSource();
    }

    public function setSource(?ContactDataSource $source): static
    {
        if (!$this->getEmailContact() instanceof EmailContactEntity) {
            $this->setEmailContact(new EmailContactEntity());
        }

        $this->emailContact->setSource($source);

        return $this;
    }

    public function hasOptInNewsletter(): bool
    {
        return $this->getEmailContact()?->hasOptInNewsletter() ?? true;
    }

    public function setOptInNewsletter(bool $optInNewsletter): static
    {
        if (!$this->getEmailContact() instanceof EmailContactEntity) {
            $this->setEmailContact(new EmailContactEntity());
        }

        $this->emailContact->setOptInNewsletter($optInNewsletter);

        return $this;
    }

    public function getUnsubscribedAt(): ?DateTimeImmutable
    {
        return $this->getEmailContact()?->getUnsubscribedAt();
    }

    public function getUnsubscribeToken(): ?string
    {
        return $this->getEmailContact()?->getUnsubscribeToken();
    }
}
