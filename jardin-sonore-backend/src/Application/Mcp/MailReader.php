<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface MailReader
{
    /** @return list<array<string, scalar|null>> */
    public function search(?string $from, ?string $since, ?string $query, int $limit): array;

    /** @return list<array<string, scalar|null>> */
    public function readThread(string $mailReference, int $limit): array;
}
