<?php

declare(strict_types=1);

namespace App\Application\Mcp;

final readonly class MailSource
{
    public function __construct(
        public string $reference,
        public string $sourceKey,
        public ?string $messageId,
        public ?string $subject,
        public string $from,
        public string $date,
    ) {
    }
}
