<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\NewsletterRecipient;

final readonly class OutreachAudienceSelection
{
    /** @param list<NewsletterRecipient> $recipients */
    public function __construct(public array $recipients, public int $excludedRecipientCount)
    {
    }
}
