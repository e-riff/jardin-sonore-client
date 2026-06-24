<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use InvalidArgumentException;

final readonly class NewsletterAudienceFilter
{
    /**
     * @param list<OrganizationType>   $organizationTypes
     * @param list<OrganizationSector> $organizationSectors
     * @param list<CustomerStatus>     $customerStatuses
     * @param list<string>             $tagUuids
     * @param list<string>             $regionCodes
     * @param list<string>             $departmentCodes
     * @param list<string>             $municipalityInseeCodes
     */
    public function __construct(
        private array $organizationTypes = [],
        private array $organizationSectors = [],
        private array $customerStatuses = [],
        private array $tagUuids = [],
        private array $regionCodes = [],
        private array $departmentCodes = [],
        private array $municipalityInseeCodes = [],
        private ?float $radiusKilometers = null,
        private ?NewsletterAudienceRadiusOrigin $radiusOrigin = null,
        private ?string $radiusOriginMunicipalityInseeCode = null,
    ) {
        $this->assertEnumList($organizationTypes, OrganizationType::class, 'organization types');
        $this->assertEnumList($organizationSectors, OrganizationSector::class, 'organization sectors');
        $this->assertEnumList($customerStatuses, CustomerStatus::class, 'customer statuses');
        $this->assertStringList($tagUuids, 'tag UUIDs');
        $this->assertStringList($regionCodes, 'region codes');
        $this->assertStringList($departmentCodes, 'department codes');
        $this->assertStringList($municipalityInseeCodes, 'municipality INSEE codes');
        $this->assertRadiusIsConsistent();
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @return list<OrganizationType>
     */
    public function getOrganizationTypes(): array
    {
        return $this->organizationTypes;
    }

    /**
     * @return list<OrganizationSector>
     */
    public function getOrganizationSectors(): array
    {
        return $this->organizationSectors;
    }

    /**
     * @return list<CustomerStatus>
     */
    public function getCustomerStatuses(): array
    {
        return $this->customerStatuses;
    }

    /**
     * @return list<string>
     */
    public function getTagUuids(): array
    {
        return $this->tagUuids;
    }

    /**
     * @return list<string>
     */
    public function getRegionCodes(): array
    {
        return $this->regionCodes;
    }

    /**
     * @return list<string>
     */
    public function getDepartmentCodes(): array
    {
        return $this->departmentCodes;
    }

    /**
     * @return list<string>
     */
    public function getMunicipalityInseeCodes(): array
    {
        return $this->municipalityInseeCodes;
    }

    public function getRadiusKilometers(): ?float
    {
        return $this->radiusKilometers;
    }

    public function getRadiusOrigin(): ?NewsletterAudienceRadiusOrigin
    {
        return $this->radiusOrigin;
    }

    public function getRadiusOriginMunicipalityInseeCode(): ?string
    {
        return $this->radiusOriginMunicipalityInseeCode;
    }

    public function hasActiveCriteria(): bool
    {
        return [] !== $this->organizationTypes
            || [] !== $this->organizationSectors
            || [] !== $this->customerStatuses
            || [] !== $this->tagUuids
            || [] !== $this->regionCodes
            || [] !== $this->departmentCodes
            || [] !== $this->municipalityInseeCodes
            || null !== $this->radiusKilometers;
    }

    /**
     * @param list<mixed>  $values
     * @param class-string $expectedClass
     */
    private function assertEnumList(array $values, string $expectedClass, string $label): void
    {
        foreach ($values as $value) {
            if (!$value instanceof $expectedClass) {
                throw new InvalidArgumentException("Newsletter audience filter {$label} must contain {$expectedClass} values only.");
            }
        }
    }

    /**
     * @param list<mixed> $values
     */
    private function assertStringList(array $values, string $label): void
    {
        foreach ($values as $value) {
            if (!is_string($value) || '' === trim($value)) {
                throw new InvalidArgumentException("Newsletter audience filter {$label} must contain non-empty strings only.");
            }
        }
    }

    private function assertRadiusIsConsistent(): void
    {
        if (null === $this->radiusKilometers) {
            if (null !== $this->radiusOrigin || null !== $this->radiusOriginMunicipalityInseeCode) {
                throw new InvalidArgumentException('Newsletter audience radius origin requires a radius.');
            }

            return;
        }

        if (0 >= $this->radiusKilometers) {
            throw new InvalidArgumentException('Newsletter audience radius must be greater than zero.');
        }

        if (!$this->radiusOrigin instanceof NewsletterAudienceRadiusOrigin) {
            throw new InvalidArgumentException('Newsletter audience radius origin is required when radius is defined.');
        }

        if (NewsletterAudienceRadiusOrigin::MUNICIPALITY === $this->radiusOrigin && (null === $this->radiusOriginMunicipalityInseeCode || '' === trim($this->radiusOriginMunicipalityInseeCode))) {
            throw new InvalidArgumentException('Newsletter audience municipality radius origin requires an INSEE code.');
        }
    }
}
