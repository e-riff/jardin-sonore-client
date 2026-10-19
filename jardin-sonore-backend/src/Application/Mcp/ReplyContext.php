<?php

declare(strict_types=1);

namespace App\Application\Mcp;

final readonly class ReplyContext
{
    public function __construct(
        public string $sourceKey,
        public ?string $messageId,
        public ?string $subject,
        public string $replyRecipient,
        public ?string $references,
        public string $mailReference,
    ) {
    }

    public function replySubject(): string
    {
        $subject = trim($this->subject ?? '');

        return 1 === preg_match('/^re\s*:/iu', $subject) ? $subject : ('Re: ' . $subject);
    }
}
