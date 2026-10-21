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
        public ?string $htmlBody = null,
    ) {
    }

    public function revision(): string
    {
        return self::contentRevision($this->body, $this->htmlBody);
    }

    public static function contentRevision(string $body, ?string $htmlBody): string
    {
        return hash('sha256', $body . (null === $htmlBody ? '' : "\0" . $htmlBody));
    }
}
