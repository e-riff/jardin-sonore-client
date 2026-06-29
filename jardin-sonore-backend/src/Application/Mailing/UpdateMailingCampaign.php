<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;

final readonly class UpdateMailingCampaign
{
    public function __construct(private MailingCampaignRepositoryInterface $mailingCampaignRepository)
    {
    }

    public function __invoke(MailingCampaign $mailingCampaign, UpdateMailingCampaignInput $input): void
    {
        $mailingCampaign->updateContent(
            internalTitle: $input->internalTitle,
            emailSubject: $input->emailSubject,
            publicTitle: $input->publicTitle,
            mainText: $input->mainText,
            templateKey: $input->templateKey,
        );

        $this->mailingCampaignRepository->save($mailingCampaign);
    }
}
