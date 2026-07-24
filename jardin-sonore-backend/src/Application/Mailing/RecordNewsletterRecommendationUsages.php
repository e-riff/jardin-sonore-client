<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterRecommendationUsage;
use App\Domain\Repository\NewsletterRecommendationUsageRepositoryInterface;

final readonly class RecordNewsletterRecommendationUsages
{
    public function __construct(
        private NewsletterRecommendationUsageRepositoryInterface $newsletterRecommendationUsageRepository,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign): void
    {
        $sourceRecommendationUuids = [];

        foreach ($mailingCampaign->getRecommendations() as $mailingRecommendation) {
            $sourceRecommendationUuid = $mailingRecommendation->getSourceRecommendationUuid();

            if (null !== $sourceRecommendationUuid) {
                $sourceRecommendationUuids[$sourceRecommendationUuid->toRfc4122()] = $sourceRecommendationUuid;
            }
        }

        foreach ($sourceRecommendationUuids as $sourceRecommendationUuid) {
            $this->newsletterRecommendationUsageRepository->save(new NewsletterRecommendationUsage(
                sourceRecommendationUuid: $sourceRecommendationUuid,
                campaignUuid: $mailingCampaign->getUuid(),
                campaignTitle: $mailingCampaign->getInternalTitle(),
                sentAt: $mailingCampaign->getUpdatedAt(),
            ));
        }
    }
}
