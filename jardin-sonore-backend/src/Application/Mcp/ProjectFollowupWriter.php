<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use DateTimeImmutable;

interface ProjectFollowupWriter
{
    /** @return array<string, mixed> */
    public function recordSummary(int $projectId, MailSource $source, string $summary): array;

    /** @return array<string, mixed> */
    public function createReminder(int $projectId, MailSource $source, string $title, DateTimeImmutable $dueOn, ?string $dueTime = null): array;
}
