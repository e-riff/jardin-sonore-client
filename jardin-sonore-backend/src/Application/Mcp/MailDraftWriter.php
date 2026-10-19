<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface MailDraftWriter
{
    /** @return array{status: string, folder: string, messageId: string, uid: ?int} */
    public function save(string $mailReference, string $body): array;
}
