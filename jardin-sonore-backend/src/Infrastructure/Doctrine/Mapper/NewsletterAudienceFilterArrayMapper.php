<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterAudienceRadiusOrigin;
use BackedEnum;
use InvalidArgumentException;

final readonly class NewsletterAudienceFilterArrayMapper
{
    /**
     * @param array<string, mixed> $audienceFilter
     */
    public function toDomain(array $audienceFilter): NewsletterAudienceFilter
    {
        return new NewsletterAudienceFilter(
            organizationTypes: $this->enumList($audienceFilter, 'organizationTypes', OrganizationType::class),
            organizationSectors: $this->enumList($audienceFilter, 'organizationSectors', OrganizationSector::class),
            customerStatuses: $this->enumList($audienceFilter, 'customerStatuses', CustomerStatus::class),
            tagUuids: $this->stringList($audienceFilter, 'tagUuids'),
            regionCodes: $this->stringList($audienceFilter, 'regionCodes'),
            departmentCodes: $this->stringList($audienceFilter, 'departmentCodes'),
            municipalityInseeCodes: $this->stringList($audienceFilter, 'municipalityInseeCodes'),
            radiusKilometers: $this->nullableFloat($audienceFilter, 'radiusKilometers'),
            radiusOrigin: $this->nullableEnum($audienceFilter, 'radiusOrigin', NewsletterAudienceRadiusOrigin::class),
            radiusOriginMunicipalityInseeCode: $this->nullableString($audienceFilter, 'radiusOriginMunicipalityInseeCode'),
            radiusOriginCustomLatitude: $this->nullableFloat($audienceFilter, 'radiusOriginCustomLatitude'),
            radiusOriginCustomLongitude: $this->nullableFloat($audienceFilter, 'radiusOriginCustomLongitude'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(NewsletterAudienceFilter $audienceFilter): array
    {
        return [
            'organizationTypes' => $this->enumValues($audienceFilter->getOrganizationTypes()),
            'organizationSectors' => $this->enumValues($audienceFilter->getOrganizationSectors()),
            'customerStatuses' => $this->enumValues($audienceFilter->getCustomerStatuses()),
            'tagUuids' => $audienceFilter->getTagUuids(),
            'regionCodes' => $audienceFilter->getRegionCodes(),
            'departmentCodes' => $audienceFilter->getDepartmentCodes(),
            'municipalityInseeCodes' => $audienceFilter->getMunicipalityInseeCodes(),
            'radiusKilometers' => $audienceFilter->getRadiusKilometers(),
            'radiusOrigin' => $audienceFilter->getRadiusOrigin()?->value,
            'radiusOriginMunicipalityInseeCode' => $audienceFilter->getRadiusOriginMunicipalityInseeCode(),
            'radiusOriginCustomLatitude' => $audienceFilter->getRadiusOriginCustomLatitude(),
            'radiusOriginCustomLongitude' => $audienceFilter->getRadiusOriginCustomLongitude(),
        ];
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<string>
     */
    private function stringList(array $values, string $key): array
    {
        $list = $values[$key] ?? [];

        if (!is_array($list)) {
            throw new InvalidArgumentException("Mailing audience filter {$key} must be a list.");
        }

        $stringList = [];

        foreach ($list as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException("Mailing audience filter {$key} must contain strings only.");
            }

            $stringList[] = $value;
        }

        return $stringList;
    }

    /**
     * @template T of BackedEnum
     *
     * @param array<string, mixed> $values
     * @param class-string<T>      $enumClass
     *
     * @return list<T>
     */
    private function enumList(array $values, string $key, string $enumClass): array
    {
        $enumList = [];

        foreach ($this->stringList($values, $key) as $value) {
            $enumList[] = $enumClass::from($value);
        }

        return $enumList;
    }

    /**
     * @template T of BackedEnum
     *
     * @param array<string, mixed> $values
     * @param class-string<T>      $enumClass
     *
     * @return T|null
     */
    private function nullableEnum(array $values, string $key, string $enumClass): ?BackedEnum
    {
        $value = $this->nullableString($values, $key);

        return null === $value ? null : $enumClass::from($value);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function nullableString(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException("Mailing audience filter {$key} must be a string.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function nullableFloat(array $values, string $key): ?float
    {
        $value = $values[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException("Mailing audience filter {$key} must be numeric.");
        }

        return (float) $value;
    }

    /**
     * @param list<BackedEnum> $enums
     *
     * @return list<string|int>
     */
    private function enumValues(array $enums): array
    {
        return array_map(static fn (BackedEnum $enum): int|string => $enum->value, $enums);
    }
}
