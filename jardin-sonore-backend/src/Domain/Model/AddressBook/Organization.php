<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use App\Domain\Model\Geo\Municipality;
use App\Domain\Model\ValueObject\PostalCode;
use Symfony\Component\Uid\Uuid;

final class Organization implements IdentifiableInterface, UuidIdentifiableInterface
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private string $name,
        private OrganizationType $type = OrganizationType::UNKNOWN,
        private OrganizationSector $sector = OrganizationSector::UNKNOWN,
        private CustomerStatus $customerStatus = CustomerStatus::UNKNOWN,
        private ?string $address = null,
        private ?PostalCode $postalCode = null,
        private ?string $city = null,
        private ?Municipality $municipality = null,
        bool $active = true,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->initializeActive($active);
        $this->assertNameIsNotBlank($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): OrganizationType
    {
        return $this->type;
    }

    public function getSector(): OrganizationSector
    {
        return $this->sector;
    }

    public function getCustomerStatus(): CustomerStatus
    {
        return $this->customerStatus;
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

    public function rename(string $name): void
    {
        $this->assertNameIsNotBlank($name);
        $this->name = $name;
    }

    private function assertNameIsNotBlank(string $name): void
    {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('Organization name cannot be blank.');
        }
    }
}
