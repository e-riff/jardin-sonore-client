<?php

declare(strict_types=1);

namespace App\Application\Mailing;

final readonly class UpdateMailingCampaignInput
{
    public function __construct(
        public string $internalTitle,
        public string $emailSubject,
        public string $publicTitle,
        public string $mainText,
        public string $templateKey,
    ) {
    }
}
