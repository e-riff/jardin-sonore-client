<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\ContactDataSource;
use App\Domain\Model\AddressBook\EmailContactType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\NullableLabelTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Symfony\Component\Uid\Uuid;

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

    private ?ContactDataSource $source = ContactDataSource::MANUAL;

    private string $unsubscribeToken = '';

    private ?\DateTimeImmutable $unsubscribedAt = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->unsubscribeToken = self::generateUnsubscribeToken();
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

    public function getSource(): ?ContactDataSource
    {
        return $this->source;
    }

    public function setSource(?ContactDataSource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getUnsubscribeToken(): string
    {
        if ('' === $this->unsubscribeToken) {
            $this->unsubscribeToken = self::generateUnsubscribeToken();
        }

        return $this->unsubscribeToken;
    }

    public function setUnsubscribeToken(?string $unsubscribeToken): static
    {
        $unsubscribeToken = null === $unsubscribeToken ? '' : trim($unsubscribeToken);
        $this->unsubscribeToken = '' === $unsubscribeToken ? self::generateUnsubscribeToken() : $unsubscribeToken;

        return $this;
    }

    public function getUnsubscribedAt(): ?\DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function setUnsubscribedAt(?\DateTimeImmutable $unsubscribedAt): static
    {
        $this->unsubscribedAt = $unsubscribedAt;

        return $this;
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribedAt instanceof \DateTimeImmutable;
    }

    private static function generateUnsubscribeToken(): string
    {
        return str_replace('-', '', Uuid::v4()->toRfc4122());
    }
}
