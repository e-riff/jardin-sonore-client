<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;

final readonly class ApplyMailingAudienceMaskToCampaign
{
    public function __construct(private MailingCampaignRepositoryInterface $mailingCampaignRepository)
    {
    }

    public function __invoke(MailingCampaign $mailingCampaign, MailingAudienceMask $mailingAudienceMask): void
    {
        $mailingCampaign->applyAudienceMask($mailingAudienceMask);
        $this->mailingCampaignRepository->save($mailingCampaign);
    }
}
