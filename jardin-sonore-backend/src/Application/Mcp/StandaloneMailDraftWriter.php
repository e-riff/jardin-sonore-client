<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface StandaloneMailDraftWriter
{
    /** @return array{status: string, folder: string, messageId: string, uid: ?int, to: string, subject: string} */
    public function create(string $recipient, string $subject, string $body): array;
}
