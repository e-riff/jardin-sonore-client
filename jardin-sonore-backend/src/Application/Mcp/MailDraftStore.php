<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface MailDraftStore
{
    /** @return list<array<string, mixed>> */
    public function search(string $folder, ?string $query, int $limit): array;

    public function findByMessageId(string $folder, string $messageId): ?int;

    public function getByMessageId(string $folder, string $messageId): ?MailDraft;

    public function append(string $folder, string $mime): ?int;

    public function deleteByMessageId(string $folder, string $messageId, int $uid): bool;
}
