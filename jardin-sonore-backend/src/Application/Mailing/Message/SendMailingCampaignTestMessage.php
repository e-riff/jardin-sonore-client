<?php

declare(strict_types=1);

namespace App\Application\Mailing\Message;

final readonly class SendMailingCampaignTestMessage
{
    public function __construct(
        public string $campaignUuid,
        public string $recipientEmail,
    ) {
    }
}
