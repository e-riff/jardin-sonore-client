<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;

final readonly class ListMailingCampaigns
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private NewsletterAudienceResolverInterface $newsletterAudienceResolver,
    ) {
    }

    /**
     * @return list<MailingCampaignSummary>
     */
    public function __invoke(): array
    {
        $summaries = [];

        foreach ($this->mailingCampaignRepository->findAllOrderedByCreatedAtDesc() as $mailingCampaign) {
            $audienceFilter = $mailingCampaign->getAudienceFilter();
            $audienceRecipientCount = null;

            if ($audienceFilter->hasActiveCriteria()) {
                try {
                    $audienceRecipientCount = $this->newsletterAudienceResolver->resolve($audienceFilter, 1)->getTotal();
                } catch (InvalidArgumentException) {
                    $audienceRecipientCount = null;
                }
            }

            $summaries[] = new MailingCampaignSummary(
                uuid: $mailingCampaign->getUuid(),
                internalTitle: $mailingCampaign->getInternalTitle(),
                emailSubject: $mailingCampaign->getEmailSubject(),
                status: $mailingCampaign->getStatus(),
                updatedAt: $mailingCampaign->getUpdatedAt(),
                hasAudienceCriteria: $audienceFilter->hasActiveCriteria(),
                audienceRecipientCount: $audienceRecipientCount,
            );
        }

        return $summaries;
    }
}
