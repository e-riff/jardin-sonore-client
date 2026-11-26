<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

enum MailingCampaignType: string
{
    case NEWSLETTER = 'newsletter';
    case OUTREACH = 'outreach';
}
