<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingCampaignRepositoryInterface;

final readonly class UpdateMailingCampaignAudience
{
    public function __construct(private MailingCampaignRepositoryInterface $mailingCampaignRepository)
    {
    }

    public function __invoke(
        MailingCampaign $mailingCampaign,
        NewsletterAudienceFilter $newsletterAudienceFilter,
    ): void {
        $mailingCampaign->updateAudienceFilter($newsletterAudienceFilter);
        $this->mailingCampaignRepository->save($mailingCampaign);
    }
}
