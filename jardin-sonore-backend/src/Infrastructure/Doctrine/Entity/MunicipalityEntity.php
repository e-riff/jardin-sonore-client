<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;

class MunicipalityEntity
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private string $name = '';

    private ?string $phoneNumber = null;

    private ?string $emailAddress = null;

    private ?string $address = null;

    private ?string $postalCode = null;

    private ?string $inseeCode = null;

    private ?string $siren = null;

    private ?string $siret = null;

    /**
     * @var array<string, mixed>|list<mixed>|null
     */
    private ?array $geoShape = null;

    private ?float $centerLatitude = null;

    private ?float $centerLongitude = null;

    private DepartmentEntity $department;

    public function __construct()
    {
        $this->initializeUuid();
    }

    public function __toString(): string
    {
        return $this->inseeCode ? sprintf('%s - %s', $this->inseeCode, $this->name) : $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getEmailAddress(): ?string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(?string $emailAddress): static
    {
        $this->emailAddress = $emailAddress;

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

    public function getInseeCode(): ?string
    {
        return $this->inseeCode;
    }

    public function setInseeCode(?string $inseeCode): static
    {
        $this->inseeCode = $inseeCode;

        return $this;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): static
    {
        $this->siren = $siren;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;

        return $this;
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    public function getGeoShape(): ?array
    {
        return $this->geoShape;
    }

    /**
     * @param array<string, mixed>|list<mixed>|null $geoShape
     */
    public function setGeoShape(?array $geoShape): static
    {
        $this->geoShape = $geoShape;

        return $this;
    }

    public function getCenterLatitude(): ?float
    {
        return $this->centerLatitude;
    }

    public function setCenterLatitude(?float $centerLatitude): static
    {
        $this->centerLatitude = $centerLatitude;

        return $this;
    }

    public function getCenterLongitude(): ?float
    {
        return $this->centerLongitude;
    }

    public function setCenterLongitude(?float $centerLongitude): static
    {
        $this->centerLongitude = $centerLongitude;

        return $this;
    }

    public function getDepartment(): ?DepartmentEntity
    {
        return $this->department ?? null;
    }

    public function setDepartment(DepartmentEntity $department): static
    {
        $this->department = $department;

        return $this;
    }
}
