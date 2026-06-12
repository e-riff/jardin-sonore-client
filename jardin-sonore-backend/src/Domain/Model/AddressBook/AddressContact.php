<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\NullableLabelTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use App\Domain\Model\Geo\Municipality;
use App\Domain\Model\ValueObject\PostalCode;
use Symfony\Component\Uid\Uuid;

final class AddressContact implements IdentifiableInterface, UuidIdentifiableInterface
{
    use ActivableTrait;
    use IdentifiableTrait;
    use NullableLabelTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private AddressContactType $type = AddressContactType::MAIN,
        private ?string $address = null,
        private ?PostalCode $postalCode = null,
        private ?string $city = null,
        private ?Municipality $municipality = null,
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

    public function getType(): AddressContactType
    {
        return $this->type;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getPostalCode(): ?PostalCode
    {
        return $this->postalCode;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getMunicipality(): ?Municipality
    {
        return $this->municipality;
    }
}
