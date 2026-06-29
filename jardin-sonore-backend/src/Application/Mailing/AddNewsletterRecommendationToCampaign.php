<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingRecommendation;
use App\Domain\Model\Mailing\NewsletterRecommendation;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;

final readonly class AddNewsletterRecommendationToCampaign
{
    public function __construct(private MailingCampaignRepositoryInterface $mailingCampaignRepository)
    {
    }

    public function __invoke(
        MailingCampaign $mailingCampaign,
        NewsletterRecommendation $newsletterRecommendation,
    ): void {
        if (!$newsletterRecommendation->isActive()) {
            throw new InvalidArgumentException('Inactive newsletter recommendation cannot be selected.');
        }

        foreach ($mailingCampaign->getRecommendations() as $mailingRecommendation) {
            if ($mailingRecommendation->getSourceRecommendationUuid()?->equals($newsletterRecommendation->getUuid())) {
                return;
            }
        }

        $recommendations = $mailingCampaign->getRecommendations();
        $recommendations[] = new MailingRecommendation(
            title: $newsletterRecommendation->getTitle(),
            text: $newsletterRecommendation->getText(),
            position: count($recommendations) + 1,
            url: $newsletterRecommendation->getUrl(),
            linkLabel: $newsletterRecommendation->getLinkLabel(),
            imagePath: $newsletterRecommendation->getImagePath(),
            sourceRecommendationUuid: $newsletterRecommendation->getUuid(),
        );

        $mailingCampaign->replaceRecommendations($recommendations);
        $this->mailingCampaignRepository->save($mailingCampaign);
    }
}
