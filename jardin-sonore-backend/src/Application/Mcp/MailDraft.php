<?php

declare(strict_types=1);

namespace App\Application\Mcp;

final readonly class MailDraft
{
    public function __construct(
        public int $uid,
        public string $messageId,
        public ?string $subject,
        public string $recipient,
        public string $body,
    ) {
    }
}
