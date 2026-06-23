<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\NullableLabelTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use App\Domain\Model\ValueObject\EmailAddress;
use Symfony\Component\Uid\Uuid;

final class EmailContact implements IdentifiableInterface, UuidIdentifiableInterface
{
    use ActivableTrait;
    use IdentifiableTrait;
    use NullableLabelTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private EmailAddress $emailAddress,
        ?string $label = null,
        private EmailContactType $type = EmailContactType::MAIN,
        private bool $optInNewsletter = true,
        bool $active = true,
        private ?ContactDataSource $source = ContactDataSource::MANUAL,
        private ?\DateTimeImmutable $unsubscribedAt = null,
        ?string $unsubscribeToken = null,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->initializeLabel($label);
        $this->initializeActive($active);
        $this->unsubscribeToken = $this->normalizeUnsubscribeToken($unsubscribeToken);
    }

    private string $unsubscribeToken;

    public function getEmailAddress(): EmailAddress
    {
        return $this->emailAddress;
    }

    public function hasNewsletterOptIn(): bool
    {
        return $this->optInNewsletter;
    }

    public function getType(): EmailContactType
    {
        return $this->type;
    }

    public function getSource(): ?ContactDataSource
    {
        return $this->source;
    }

    public function getUnsubscribeToken(): string
    {
        return $this->unsubscribeToken;
    }

    public function getUnsubscribedAt(): ?\DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribedAt instanceof \DateTimeImmutable;
    }

    public function unsubscribe(?\DateTimeImmutable $unsubscribedAt = null): void
    {
        $this->unsubscribedAt = $unsubscribedAt ?? new \DateTimeImmutable();
        $this->optInNewsletter = false;
    }

    public function resubscribe(): void
    {
        $this->unsubscribedAt = null;
        $this->optInNewsletter = true;
    }

    public function isReachableForNewsletter(): bool
    {
        return $this->active && $this->optInNewsletter && !$this->isUnsubscribed();
    }

    private function normalizeUnsubscribeToken(?string $unsubscribeToken): string
    {
        $unsubscribeToken = null === $unsubscribeToken ? '' : trim($unsubscribeToken);

        return '' === $unsubscribeToken ? str_replace('-', '', Uuid::v4()->toRfc4122()) : $unsubscribeToken;
    }
}
