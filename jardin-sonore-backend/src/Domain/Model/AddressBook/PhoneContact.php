<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\NullableLabelTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use App\Domain\Model\ValueObject\PhoneNumber;
use Symfony\Component\Uid\Uuid;

final class PhoneContact implements IdentifiableInterface, UuidIdentifiableInterface
{
    use ActivableTrait;
    use IdentifiableTrait;
    use NullableLabelTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private PhoneNumber $phoneNumber,
        private ?Organization $organization = null,
        private ?Person $person = null,
        ?string $label = null,
        bool $active = true,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->initializeLabel($label);
        $this->initializeActive($active);
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function getPerson(): ?Person
    {
        return $this->person;
    }
}
