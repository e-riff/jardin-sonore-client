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
        private ContactDataSource $source = ContactDataSource::MANUAL,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->initializeLabel($label);
        $this->initializeActive($active);
    }

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

    public function getSource(): ContactDataSource
    {
        return $this->source;
    }

    public function isReachableForNewsletter(): bool
    {
        return $this->active && $this->optInNewsletter;
    }
}
