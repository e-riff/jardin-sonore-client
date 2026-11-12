<?php

declare(strict_types=1);

namespace App\Application\Mcp;

final readonly class EditStandaloneMailDraft implements StandaloneMailDraftEditor
{
    public function __construct(private GeneralMailDraftEditor $editor)
    {
    }

    public function read(string $draftMessageId): array
    {
        return $this->editor->read($draftMessageId);
    }

    public function update(string $draftMessageId, string $expectedRevision, string $body, ?string $htmlBody = null): array
    {
        $changes = ['body' => $body];
        if (null !== $htmlBody) {
            $changes['htmlBody'] = $htmlBody;
        }

        return $this->editor->update($draftMessageId, $expectedRevision, $changes);
    }
}
