<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterAudienceRadiusOrigin;
use Symfony\Component\Validator\Constraints as Assert;

final class MailingAudienceFormModel
{
    /**
     * @var list<OrganizationType>
     */
    public array $organizationTypes = [];

    /**
     * @var list<OrganizationSector>
     */
    public array $organizationSectors = [];

    /**
     * @var list<CustomerStatus>
     */
    public array $customerStatuses = [];

    /**
     * @var list<string>
     */
    public array $tagUuids = [];

    /**
     * @var list<string>
     */
    public array $organizationUuids = [];

    /**
     * @var list<string>
     */
    public array $regionCodes = [];

    /**
     * @var list<string>
     */
    public array $departmentCodes = [];

    /**
     * @var list<string>
     */
    public array $municipalityInseeCodes = [];

    #[Assert\Positive]
    public ?float $radiusKilometers = 1.0;

    public MailingAudienceGeographicMode $geographicMode = MailingAudienceGeographicMode::MUNICIPALITIES;

    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'mailing.audience.validation.insee_code')]
    public ?string $radiusOriginMunicipalityInseeCode = null;

    #[Assert\Range(min: -90, max: 90, notInRangeMessage: 'mailing.audience.validation.latitude')]
    public ?float $radiusOriginCustomLatitude = null;

    #[Assert\Range(min: -180, max: 180, notInRangeMessage: 'mailing.audience.validation.longitude')]
    public ?float $radiusOriginCustomLongitude = null;

    public static function fromAudienceFilter(NewsletterAudienceFilter $newsletterAudienceFilter): self
    {
        $formModel = new self();
        $formModel->organizationTypes = $newsletterAudienceFilter->getOrganizationTypes();
        $formModel->organizationSectors = $newsletterAudienceFilter->getOrganizationSectors();
        $formModel->customerStatuses = $newsletterAudienceFilter->getCustomerStatuses();
        $formModel->organizationUuids = $newsletterAudienceFilter->getOrganizationUuids();
        $formModel->tagUuids = $newsletterAudienceFilter->getTagUuids();
        $formModel->regionCodes = $newsletterAudienceFilter->getRegionCodes();
        $formModel->departmentCodes = $newsletterAudienceFilter->getDepartmentCodes();
        $formModel->municipalityInseeCodes = $newsletterAudienceFilter->getMunicipalityInseeCodes();
        $formModel->radiusKilometers = $newsletterAudienceFilter->getRadiusKilometers() ?? 1.0;
        $formModel->geographicMode = match ($newsletterAudienceFilter->getRadiusOrigin()) {
            NewsletterAudienceRadiusOrigin::HOME => MailingAudienceGeographicMode::HOME_RADIUS,
            NewsletterAudienceRadiusOrigin::CUSTOM => MailingAudienceGeographicMode::CUSTOM_RADIUS,
            default => MailingAudienceGeographicMode::MUNICIPALITIES,
        };
        $formModel->radiusOriginMunicipalityInseeCode = $newsletterAudienceFilter->getRadiusOriginMunicipalityInseeCode();
        $formModel->radiusOriginCustomLatitude = $newsletterAudienceFilter->getRadiusOriginCustomLatitude();
        $formModel->radiusOriginCustomLongitude = $newsletterAudienceFilter->getRadiusOriginCustomLongitude();

        return $formModel;
    }

    public function toAudienceFilter(): NewsletterAudienceFilter
    {
        $organizationUuids = self::normalizeStringList($this->organizationUuids);
        $tagUuids = self::normalizeStringList($this->tagUuids);
        $municipalityInseeCodes = self::normalizeStringList($this->municipalityInseeCodes);

        return new NewsletterAudienceFilter(
            organizationTypes: $this->organizationTypes,
            organizationSectors: $this->organizationSectors,
            customerStatuses: $this->customerStatuses,
            organizationUuids: $organizationUuids,
            tagUuids: $tagUuids,
            municipalityInseeCodes: MailingAudienceGeographicMode::MUNICIPALITIES === $this->geographicMode ? $municipalityInseeCodes : [],
            radiusKilometers: $this->hasRadiusMode() ? ($this->radiusKilometers ?? 1.0) : null,
            radiusOrigin: match ($this->geographicMode) {
                MailingAudienceGeographicMode::HOME_RADIUS => NewsletterAudienceRadiusOrigin::HOME,
                MailingAudienceGeographicMode::MUNICIPALITY_RADIUS => NewsletterAudienceRadiusOrigin::MUNICIPALITY,
                MailingAudienceGeographicMode::CUSTOM_RADIUS => NewsletterAudienceRadiusOrigin::CUSTOM,
                default => null,
            },
            radiusOriginMunicipalityInseeCode: MailingAudienceGeographicMode::MUNICIPALITY_RADIUS === $this->geographicMode
                ? self::normalizeNullableString($this->radiusOriginMunicipalityInseeCode)
                : null,
            radiusOriginCustomLatitude: MailingAudienceGeographicMode::CUSTOM_RADIUS === $this->geographicMode
                ? self::normalizeNullableFloat($this->radiusOriginCustomLatitude)
                : null,
            radiusOriginCustomLongitude: MailingAudienceGeographicMode::CUSTOM_RADIUS === $this->geographicMode
                ? self::normalizeNullableFloat($this->radiusOriginCustomLongitude)
                : null,
        );
    }

    public function hasAdministrativeLocationCriteria(): bool
    {
        return MailingAudienceGeographicMode::MUNICIPALITIES === $this->geographicMode && [] !== $this->municipalityInseeCodes;
    }

    public function hasSelectedRadiusOrigin(): bool
    {
        return $this->hasRadiusMode();
    }

    public function hasRadiusCriteria(): bool
    {
        return $this->hasRadiusMode()
            || null !== $this->radiusOriginCustomLatitude
            || null !== $this->radiusOriginCustomLongitude;
    }

    public function isMunicipalitiesMode(): bool
    {
        return MailingAudienceGeographicMode::MUNICIPALITIES === $this->geographicMode;
    }

    public function isCustomRadiusOrigin(): bool
    {
        return MailingAudienceGeographicMode::CUSTOM_RADIUS === $this->geographicMode;
    }

    public function getRadiusOriginValue(): ?string
    {
        return match ($this->geographicMode) {
            MailingAudienceGeographicMode::HOME_RADIUS => NewsletterAudienceRadiusOrigin::HOME->value,
            MailingAudienceGeographicMode::MUNICIPALITY_RADIUS => NewsletterAudienceRadiusOrigin::MUNICIPALITY->value,
            MailingAudienceGeographicMode::CUSTOM_RADIUS => NewsletterAudienceRadiusOrigin::CUSTOM->value,
            default => null,
        };
    }

    public function hasRadiusMode(): bool
    {
        return MailingAudienceGeographicMode::HOME_RADIUS === $this->geographicMode
            || MailingAudienceGeographicMode::MUNICIPALITY_RADIUS === $this->geographicMode
            || MailingAudienceGeographicMode::CUSTOM_RADIUS === $this->geographicMode;
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function normalizeStringList(array $values): array
    {
        $normalizedValues = [];

        foreach ($values as $value) {
            $normalizedValue = trim($value);

            if ('' === $normalizedValue) {
                continue;
            }

            if (in_array($normalizedValue, $normalizedValues, true)) {
                continue;
            }

            $normalizedValues[] = $normalizedValue;
        }

        return $normalizedValues;
    }

    private static function normalizeNullableFloat(?float $value): ?float
    {
        return null === $value ? null : (float) $value;
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
