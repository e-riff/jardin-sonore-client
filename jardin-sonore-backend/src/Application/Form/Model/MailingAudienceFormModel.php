<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterAudienceRadiusOrigin;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

final class MailingAudienceFormModel
{
    public string $maskName = '';

    /**
     * @var list<OrganizationType>
     */
    public array $organizationTypes = [];

    public bool $includeFreeSubscribers = false;

    /**
     * @var list<OrganizationSector>
     */
    public array $organizationSectors = [];

    public string $legacyTagUuidsJson = '[]';

    public string $directoryEntryUuidsJson = '[]';

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
        $formModel->includeFreeSubscribers = $newsletterAudienceFilter->includesFreeSubscribers();
        $formModel->organizationTypes = $newsletterAudienceFilter->getOrganizationTypes();
        $formModel->organizationSectors = $newsletterAudienceFilter->getOrganizationSectors();
        $formModel->organizationUuids = $newsletterAudienceFilter->getOrganizationUuids();
        $formModel->legacyTagUuidsJson = json_encode($newsletterAudienceFilter->getTagUuids(), JSON_THROW_ON_ERROR);
        $formModel->directoryEntryUuidsJson = json_encode($newsletterAudienceFilter->getDirectoryEntryUuids(), JSON_THROW_ON_ERROR);
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
        $legacyTagUuids = self::decodeStringList($this->legacyTagUuidsJson);
        $directoryEntryUuids = self::decodeStringList($this->directoryEntryUuidsJson);
        $municipalityInseeCodes = self::normalizeStringList($this->municipalityInseeCodes);

        return new NewsletterAudienceFilter(
            includeFreeSubscribers: $this->includeFreeSubscribers,
            organizationTypes: $this->organizationTypes,
            organizationSectors: $this->organizationSectors,
            organizationUuids: $organizationUuids,
            tagUuids: $legacyTagUuids,
            directoryEntryUuids: $directoryEntryUuids,
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

    /** @return list<string> */
    private static function decodeStringList(string $json): array
    {
        $values = json_decode($json, true);
        if (!is_array($values)) {
            throw new InvalidArgumentException('Legacy mailing audience targeting is invalid.');
        }

        return self::normalizeStringList(array_values(array_filter($values, 'is_string')));
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
