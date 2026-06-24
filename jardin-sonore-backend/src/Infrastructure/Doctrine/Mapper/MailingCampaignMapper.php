<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterAudienceRadiusOrigin;
use App\Infrastructure\Doctrine\Entity\MailingCampaignEntity;
use BackedEnum;
use InvalidArgumentException;

final readonly class MailingCampaignMapper
{
    public function __construct(private MailingRecommendationMapper $mailingRecommendationMapper)
    {
    }

    public function toDomain(MailingCampaignEntity $mailingCampaignEntity): MailingCampaign
    {
        $recommendations = [];

        foreach ($mailingCampaignEntity->getRecommendations() as $mailingRecommendationEntity) {
            $recommendations[] = $this->mailingRecommendationMapper->toDomain($mailingRecommendationEntity);
        }

        return new MailingCampaign(
            internalTitle: $mailingCampaignEntity->getInternalTitle(),
            emailSubject: $mailingCampaignEntity->getEmailSubject(),
            publicTitle: $mailingCampaignEntity->getPublicTitle(),
            mainText: $mailingCampaignEntity->getMainText(),
            templateKey: $mailingCampaignEntity->getTemplateKey(),
            audienceFilter: $this->audienceFilterToDomain($mailingCampaignEntity->getAudienceFilter()),
            status: $mailingCampaignEntity->getStatus(),
            recommendations: $recommendations,
            createdAt: $mailingCampaignEntity->getCreatedAt(),
            updatedAt: $mailingCampaignEntity->getUpdatedAt(),
            lastTestSentAt: $mailingCampaignEntity->getLastTestSentAt(),
            uuid: $mailingCampaignEntity->getUuid(),
        );
    }

    public function toEntity(MailingCampaign $mailingCampaign, ?MailingCampaignEntity $mailingCampaignEntity = null): MailingCampaignEntity
    {
        $mailingCampaignEntity ??= new MailingCampaignEntity();

        $mailingCampaignEntity
            ->setUuid($mailingCampaign->getUuid())
            ->setInternalTitle($mailingCampaign->getInternalTitle())
            ->setEmailSubject($mailingCampaign->getEmailSubject())
            ->setPublicTitle($mailingCampaign->getPublicTitle())
            ->setMainText($mailingCampaign->getMainText())
            ->setTemplateKey($mailingCampaign->getTemplateKey())
            ->setStatus($mailingCampaign->getStatus())
            ->setAudienceFilter($this->audienceFilterToArray($mailingCampaign->getAudienceFilter()))
            ->setCreatedAt($mailingCampaign->getCreatedAt())
            ->setUpdatedAt($mailingCampaign->getUpdatedAt())
            ->setLastTestSentAt($mailingCampaign->getLastTestSentAt())
            ->clearRecommendations();

        foreach ($mailingCampaign->getRecommendations() as $mailingRecommendation) {
            $mailingCampaignEntity->addRecommendation($this->mailingRecommendationMapper->toEntity($mailingRecommendation));
        }

        return $mailingCampaignEntity;
    }

    /**
     * @param array<string, mixed> $audienceFilter
     */
    private function audienceFilterToDomain(array $audienceFilter): NewsletterAudienceFilter
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
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function audienceFilterToArray(NewsletterAudienceFilter $audienceFilter): array
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
