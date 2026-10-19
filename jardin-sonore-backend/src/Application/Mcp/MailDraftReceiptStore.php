<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface MailDraftReceiptStore
{
    public function reserve(string $sourceKey, string $draftMessageId, string $folder): DraftReceipt;

    public function markSaved(string $sourceKey, ?int $uid): void;

    public function markUncertain(string $sourceKey): void;
}
