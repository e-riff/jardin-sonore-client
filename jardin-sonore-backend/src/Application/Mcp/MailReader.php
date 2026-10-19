<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface MailReader
{
    public function source(string $mailReference): MailSource;

    public function replyContext(string $mailReference): ReplyContext;

    /** @return array{matchable: bool, replyRecipient: string, replySubject: string, replies: list<array{date: string, to: string, subject: ?string, body: string}>} */
    public function findSentReplies(string $mailReference, int $limit): array;

    /** @return list<array<string, scalar|null>> */
    public function search(?string $from, ?string $since, ?string $query, int $limit): array;

    /** @return list<array<string, scalar|null>> */
    public function readThread(string $mailReference, int $limit): array;
}
