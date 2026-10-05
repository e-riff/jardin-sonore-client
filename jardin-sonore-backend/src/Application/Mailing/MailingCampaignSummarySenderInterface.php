<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;

interface MailingCampaignSummarySenderInterface
{
    /**
     * @param array<string, int> $deliveryCounts
     */
    public function send(MailingCampaign $mailingCampaign, array $deliveryCounts): void;
}
