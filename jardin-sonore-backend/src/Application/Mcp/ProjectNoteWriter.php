<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use DateTimeImmutable;

interface ProjectNoteWriter
{
    /** @return array<string, mixed> */
    public function create(int $projectId, string $requestId, string $content, ?DateTimeImmutable $occurredOn): array;
}
