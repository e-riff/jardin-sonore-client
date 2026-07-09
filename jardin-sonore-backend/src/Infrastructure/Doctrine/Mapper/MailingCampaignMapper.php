<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Infrastructure\Doctrine\Entity\MailingCampaignEntity;

final readonly class MailingCampaignMapper
{
    public function __construct(
        private MailingRecommendationMapper $mailingRecommendationMapper,
        private NewsletterAudienceFilterArrayMapper $newsletterAudienceFilterArrayMapper,
    ) {
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
            subtitle: $mailingCampaignEntity->getSubtitle(),
            callToActionLabel: $mailingCampaignEntity->getCallToActionLabel(),
            callToActionUrl: $mailingCampaignEntity->getCallToActionUrl(),
            bannerImagePath: $mailingCampaignEntity->getBannerImagePath(),
            templateKey: $mailingCampaignEntity->getTemplateKey(),
            audienceFilter: $this->newsletterAudienceFilterArrayMapper->toDomain($mailingCampaignEntity->getAudienceFilter()),
            status: $mailingCampaignEntity->getStatus(),
            recommendations: $recommendations,
            createdAt: $mailingCampaignEntity->getCreatedAt(),
            updatedAt: $mailingCampaignEntity->getUpdatedAt(),
            lastTestSentAt: $mailingCampaignEntity->getLastTestSentAt(),
            appliedAudienceMaskUuid: $mailingCampaignEntity->getAppliedAudienceMaskUuid(),
            appliedAudienceMaskName: $mailingCampaignEntity->getAppliedAudienceMaskName(),
            uuid: $mailingCampaignEntity->getUuid(),
        );
    }

    public function toEntity(MailingCampaign $mailingCampaign, ?MailingCampaignEntity $mailingCampaignEntity = null): MailingCampaignEntity
    {
        $mailingCampaignEntity ??= new MailingCampaignEntity();
        $existingRecommendationEntities = [];

        foreach ($mailingCampaignEntity->getRecommendations() as $mailingRecommendationEntity) {
            $existingRecommendationEntities[$mailingRecommendationEntity->getUuid()->toRfc4122()] = $mailingRecommendationEntity;
        }

        $mailingCampaignEntity
            ->setUuid($mailingCampaign->getUuid())
            ->setInternalTitle($mailingCampaign->getInternalTitle())
            ->setEmailSubject($mailingCampaign->getEmailSubject())
            ->setPublicTitle($mailingCampaign->getPublicTitle())
            ->setMainText($mailingCampaign->getMainText())
            ->setSubtitle($mailingCampaign->getSubtitle())
            ->setCallToActionLabel($mailingCampaign->getCallToActionLabel())
            ->setCallToActionUrl($mailingCampaign->getCallToActionUrl())
            ->setBannerImagePath($mailingCampaign->getBannerImagePath())
            ->setTemplateKey($mailingCampaign->getTemplateKey())
            ->setStatus($mailingCampaign->getStatus())
            ->setAudienceFilter($this->newsletterAudienceFilterArrayMapper->toArray($mailingCampaign->getAudienceFilter()))
            ->setCreatedAt($mailingCampaign->getCreatedAt())
            ->setUpdatedAt($mailingCampaign->getUpdatedAt())
            ->setLastTestSentAt($mailingCampaign->getLastTestSentAt())
            ->setAppliedAudienceMaskUuid($mailingCampaign->getAppliedAudienceMaskUuid())
            ->setAppliedAudienceMaskName($mailingCampaign->getAppliedAudienceMaskName());

        $retainedRecommendationUuids = [];

        foreach ($mailingCampaign->getRecommendations() as $mailingRecommendation) {
            $uuid = $mailingRecommendation->getUuid()->toRfc4122();
            $mailingRecommendationEntity = $this->mailingRecommendationMapper->toEntity(
                mailingRecommendation: $mailingRecommendation,
                mailingRecommendationEntity: $existingRecommendationEntities[$uuid] ?? null,
            );
            $mailingCampaignEntity->addRecommendation($mailingRecommendationEntity);
            $retainedRecommendationUuids[$uuid] = true;
        }

        foreach ($existingRecommendationEntities as $uuid => $mailingRecommendationEntity) {
            if (!isset($retainedRecommendationUuids[$uuid])) {
                $mailingCampaignEntity->removeRecommendation($mailingRecommendationEntity);
            }
        }

        return $mailingCampaignEntity;
    }
}
