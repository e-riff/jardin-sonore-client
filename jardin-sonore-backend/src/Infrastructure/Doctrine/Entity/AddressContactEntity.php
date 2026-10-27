<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\AddressContactType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\NullableLabelTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Symfony\Component\String\UnicodeString;

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

    private ?MunicipalityEntity $municipality = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
    }

    public function __toString(): string
    {
        return trim(implode(' ', array_filter([$this->getStreetAddress(), $this->municipality?->getPostalCode(), $this->municipality?->getName()])));
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

    public function setType(?AddressContactType $type): static
    {
        if ($type instanceof AddressContactType) {
            $this->type = $type;
        }

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

    public function getStreetAddress(): ?string
    {
        $address = trim((string) $this->address);
        $municipality = $this->municipality;
        $postalCode = $municipality?->getPostalCode();
        $municipalityName = $municipality?->getName();

        if ('' === $address || null === $postalCode || null === $municipalityName) {
            return '' === $address ? null : $address;
        }

        $normalizedAddress = (new UnicodeString($address))->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
        $normalizedLocality = (new UnicodeString($postalCode . $municipalityName))->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
        $normalizedPostalCode = (new UnicodeString($postalCode))->ascii()->lower()->toString();
        $postalCodePosition = strrpos($address, $postalCode);

        if (str_ends_with($normalizedAddress, $normalizedLocality) && false !== $postalCodePosition) {
            $address = rtrim(substr($address, 0, $postalCodePosition), " \t\n\r\0\x0B,;·-");
        } elseif (str_ends_with($normalizedAddress, $normalizedPostalCode)) {
            $address = rtrim(substr($address, 0, $postalCodePosition), " \t\n\r\0\x0B,;·-");
        }

        return '' === $address ? null : $address;
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
