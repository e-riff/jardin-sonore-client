<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface ProjectContextReader
{
    /** @return array<string, mixed> */
    public function get(int $projectId): array;
}
