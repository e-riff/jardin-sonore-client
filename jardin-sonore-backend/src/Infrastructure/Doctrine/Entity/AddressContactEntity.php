<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\AddressContactType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\NullableLabelTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;

class AddressContactEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use NullableLabelTrait;
    use TimestampableTrait;
    use UuidIdentifiableTrait;

    // transient null allowed for orphan removal
    // @phpstan-ignore-next-line
    private ?ContactDetailsEntity $contactDetails = null;

    private AddressContactType $type = AddressContactType::MAIN;

    private ?string $address = null;

    private ?string $postalCode = null;

    private ?string $city = null;

    private ?MunicipalityEntity $municipality = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
    }

    public function __toString(): string
    {
        return trim(implode(' ', array_filter([$this->address, $this->postalCode, $this->city])));
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

    public function getType(): AddressContactType
    {
        return $this->type;
    }

    public function setType(AddressContactType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getMunicipality(): ?MunicipalityEntity
    {
        return $this->municipality;
    }

    public function setMunicipality(?MunicipalityEntity $municipality): static
    {
        $this->municipality = $municipality;

        return $this;
    }
}
