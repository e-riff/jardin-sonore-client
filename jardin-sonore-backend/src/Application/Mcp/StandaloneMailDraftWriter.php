<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface StandaloneMailDraftWriter
{
    /** @param list<array<string, mixed>> $attachments
     * @return array<string, mixed>
     */
    public function create(string $recipient, string $subject, string $body, ?string $htmlBody = null, array $attachments = []): array;
}
