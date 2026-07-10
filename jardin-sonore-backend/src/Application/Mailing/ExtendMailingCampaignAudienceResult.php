<?php

declare(strict_types=1);

namespace App\Application\Mailing;

final readonly class ExtendMailingCampaignAudienceResult
{
    public function __construct(
        public int $matchedRecipientCount,
        public int $alreadyLinkedRecipientCount,
        public int $newRecipientCount,
    ) {
    }
}
