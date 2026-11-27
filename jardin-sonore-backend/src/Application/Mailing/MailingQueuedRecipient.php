<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\NewsletterRecipient;

final readonly class MailingQueuedRecipient
{
    public function __construct(public string $campaignUuid, public string $status, public NewsletterRecipient $recipient)
    {
    }
}
