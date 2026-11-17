<?php

declare(strict_types=1);

namespace App\Application\Mcp;

final readonly class ReplyContext
{
    /** @param list<string> $to
     * @param list<string> $cc
     */
    public function __construct(
        public string $sourceKey,
        public ?string $messageId,
        public ?string $subject,
        public string $replyRecipient,
        public ?string $references,
        public string $mailReference,
        public array $to = [],
        public array $cc = [],
    ) {
    }

    public function replySubject(): string
    {
        $subject = trim($this->subject ?? '');

        return 1 === preg_match('/^re\s*:/iu', $subject) ? $subject : ('Re: ' . $subject);
    }
}
