<?php

declare(strict_types=1);

namespace App\Application\Mailing\Message;

final readonly class SendMailingCampaignRecipientMessage
{
    public function __construct(
        public int $deliveryRecipientId,
        public string $campaignUuid,
        public string $recipientEmail,
        public string $unsubscribeToken,
        public ?string $displayName = null,
    ) {
    }
}
