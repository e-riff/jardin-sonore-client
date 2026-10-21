<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface StandaloneMailDraftEditor
{
    /** @return array<string, mixed> */
    public function read(string $draftMessageId): array;

    /** @return array<string, mixed> */
    public function update(string $draftMessageId, string $expectedRevision, string $body, ?string $htmlBody = null): array;
}
