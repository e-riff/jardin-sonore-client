<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface MailReader
{
    public function source(string $mailReference): MailSource;

    /** @return list<array<string, scalar|null>> */
    public function search(?string $from, ?string $since, ?string $query, int $limit): array;

    /** @return list<array<string, scalar|null>> */
    public function readThread(string $mailReference, int $limit): array;
}
