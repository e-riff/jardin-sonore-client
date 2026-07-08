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

    public ?NewsletterAudienceRadiusOrigin $radiusOrigin = null;

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
        $formModel->tagUuids = $newsletterAudienceFilter->getTagUuids();
        $formModel->regionCodes = $newsletterAudienceFilter->getRegionCodes();
        $formModel->departmentCodes = $newsletterAudienceFilter->getDepartmentCodes();
        $formModel->municipalityInseeCodes = $newsletterAudienceFilter->getMunicipalityInseeCodes();
        $formModel->radiusKilometers = $newsletterAudienceFilter->getRadiusKilometers() ?? 1.0;
        $formModel->radiusOrigin = $newsletterAudienceFilter->getRadiusOrigin();
        $formModel->radiusOriginMunicipalityInseeCode = $newsletterAudienceFilter->getRadiusOriginMunicipalityInseeCode();
        $formModel->radiusOriginCustomLatitude = $newsletterAudienceFilter->getRadiusOriginCustomLatitude();
        $formModel->radiusOriginCustomLongitude = $newsletterAudienceFilter->getRadiusOriginCustomLongitude();

        return $formModel;
    }

    public function toAudienceFilter(): NewsletterAudienceFilter
    {
        return new NewsletterAudienceFilter(
            organizationTypes: $this->organizationTypes,
            organizationSectors: $this->organizationSectors,
            customerStatuses: $this->customerStatuses,
            tagUuids: $this->tagUuids,
            regionCodes: $this->regionCodes,
            departmentCodes: $this->departmentCodes,
            municipalityInseeCodes: $this->municipalityInseeCodes,
            radiusKilometers: null !== $this->radiusOrigin ? ($this->radiusKilometers ?? 1.0) : null,
            radiusOrigin: $this->radiusOrigin,
            radiusOriginMunicipalityInseeCode: NewsletterAudienceRadiusOrigin::MUNICIPALITY === $this->radiusOrigin
                ? $this->radiusOriginMunicipalityInseeCode
                : null,
            radiusOriginCustomLatitude: NewsletterAudienceRadiusOrigin::CUSTOM === $this->radiusOrigin
                ? $this->radiusOriginCustomLatitude
                : null,
            radiusOriginCustomLongitude: NewsletterAudienceRadiusOrigin::CUSTOM === $this->radiusOrigin
                ? $this->radiusOriginCustomLongitude
                : null,
        );
    }

    public function hasAdministrativeLocationCriteria(): bool
    {
        return [] !== $this->regionCodes
            || [] !== $this->departmentCodes
            || [] !== $this->municipalityInseeCodes;
    }

    public function hasSelectedRadiusOrigin(): bool
    {
        return null !== $this->radiusOrigin;
    }

    public function hasRadiusCriteria(): bool
    {
        return null !== $this->radiusOrigin
            || null !== $this->radiusOriginMunicipalityInseeCode
            || null !== $this->radiusOriginCustomLatitude
            || null !== $this->radiusOriginCustomLongitude;
    }

    public function isMunicipalityRadiusOrigin(): bool
    {
        return NewsletterAudienceRadiusOrigin::MUNICIPALITY === $this->radiusOrigin;
    }

    public function isCustomRadiusOrigin(): bool
    {
        return NewsletterAudienceRadiusOrigin::CUSTOM === $this->radiusOrigin;
    }

    public function getRadiusOriginValue(): ?string
    {
        return $this->radiusOrigin?->value;
    }
}
