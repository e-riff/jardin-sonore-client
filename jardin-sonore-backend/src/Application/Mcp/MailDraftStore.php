<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface MailDraftStore
{
    public function findByMessageId(string $folder, string $messageId): ?int;

    public function append(string $folder, string $mime): ?int;
}
