<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface MailDraftEditor
{
    /** @return array<string, mixed> */
    public function read(string $mailReference): array;

    /** @param list<array<string, mixed>> $attachments
     * @param list<string> $removeAttachmentIds
     *
     * @return array<string, mixed>
     */
    public function update(string $mailReference, string $expectedDraftMessageId, string $expectedRevision, string $body, ?string $htmlBody = null, array $attachments = [], array $removeAttachmentIds = []): array;
}
