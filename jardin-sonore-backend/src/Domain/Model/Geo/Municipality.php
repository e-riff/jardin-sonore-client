<?php

declare(strict_types=1);

namespace App\Domain\Model\Geo;

use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Model\ValueObject\InseeCode;
use App\Domain\Model\ValueObject\PhoneNumber;
use App\Domain\Model\ValueObject\PostalCode;
use App\Domain\Model\ValueObject\Siren;
use App\Domain\Model\ValueObject\Siret;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class Municipality implements IdentifiableInterface, UuidIdentifiableInterface
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    /**
     * @param array<string, mixed>|list<mixed>|null $geoShape
     */
    public function __construct(
        private string $name,
        private Department $department,
        private ?PhoneNumber $phoneNumber = null,
        private ?EmailAddress $emailAddress = null,
        private ?string $address = null,
        private ?PostalCode $postalCode = null,
        private ?InseeCode $inseeCode = null,
        private ?Siren $siren = null,
        private ?Siret $siret = null,
        private ?array $geoShape = null,
        private ?float $centerLatitude = null,
        private ?float $centerLongitude = null,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->assertNameIsNotBlank($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function getPhoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function getEmailAddress(): ?EmailAddress
    {
        return $this->emailAddress;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getPostalCode(): ?PostalCode
    {
        return $this->postalCode;
    }

    public function getInseeCode(): ?InseeCode
    {
        return $this->inseeCode;
    }

    public function getSiren(): ?Siren
    {
        return $this->siren;
    }

    public function getSiret(): ?Siret
    {
        return $this->siret;
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    public function getGeoShape(): ?array
    {
        return $this->geoShape;
    }

    public function getCenterLatitude(): ?float
    {
        return $this->centerLatitude;
    }

    public function getCenterLongitude(): ?float
    {
        return $this->centerLongitude;
    }

    public function rename(string $name): void
    {
        $this->assertNameIsNotBlank($name);
        $this->name = $name;
    }

    public function attachToDepartment(Department $department): void
    {
        $this->department = $department;
    }

    public function updateContactDetails(?PhoneNumber $phoneNumber, ?EmailAddress $emailAddress, ?string $address): void
    {
        $this->phoneNumber = $phoneNumber;
        $this->emailAddress = $emailAddress;
        $this->address = $address;
    }

    public function updateAdministrativeCodes(?PostalCode $postalCode, ?InseeCode $inseeCode, ?Siren $siren, ?Siret $siret): void
    {
        $this->postalCode = $postalCode;
        $this->inseeCode = $inseeCode;
        $this->siren = $siren;
        $this->siret = $siret;
    }

    /**
     * @param array<string, mixed>|list<mixed>|null $geoShape
     */
    public function updateGeography(?array $geoShape): void
    {
        $this->geoShape = $geoShape;
    }

    public function updateCenter(?float $centerLatitude, ?float $centerLongitude): void
    {
        $this->centerLatitude = $centerLatitude;
        $this->centerLongitude = $centerLongitude;
    }

    private function assertNameIsNotBlank(string $name): void
    {
        if ('' === trim($name)) {
            throw new InvalidArgumentException('Municipality name cannot be blank.');
        }
    }
}
