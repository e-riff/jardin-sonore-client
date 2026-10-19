<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface ProjectSearch
{
    /** @return list<array<string, scalar|null>> */
    public function search(?string $email, ?string $organization, ?string $terms, int $limit): array;
}
