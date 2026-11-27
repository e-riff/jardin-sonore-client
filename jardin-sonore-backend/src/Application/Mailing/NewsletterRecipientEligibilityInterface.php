<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaignType;

interface NewsletterRecipientEligibilityInterface
{
    public function isEligible(string $emailAddress, ?MailingCampaignType $campaignType = null): bool;
}
