<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface MailDraftEditor
{
    /** @return array<string, mixed> */
    public function read(string $mailReference): array;

    /** @return array<string, mixed> */
    public function update(string $mailReference, string $expectedDraftMessageId, string $expectedRevision, string $body, ?string $htmlBody = null): array;
}
