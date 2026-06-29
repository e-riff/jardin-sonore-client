<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Repository\MailingCampaignRepositoryInterface;

final readonly class ListMailingCampaigns
{
    public function __construct(private MailingCampaignRepositoryInterface $mailingCampaignRepository)
    {
    }

    /**
     * @return list<MailingCampaignSummary>
     */
    public function __invoke(): array
    {
        $summaries = [];

        foreach ($this->mailingCampaignRepository->findAllOrderedByCreatedAtDesc() as $mailingCampaign) {
            $summaries[] = new MailingCampaignSummary(
                uuid: $mailingCampaign->getUuid(),
                internalTitle: $mailingCampaign->getInternalTitle(),
                emailSubject: $mailingCampaign->getEmailSubject(),
                status: $mailingCampaign->getStatus(),
                updatedAt: $mailingCampaign->getUpdatedAt(),
            );
        }

        return $summaries;
    }
}
