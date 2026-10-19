<?php

declare(strict_types=1);

namespace App\Application\Mcp;

final readonly class DraftReceipt
{
    public function __construct(
        public string $sourceKey,
        public string $messageId,
        public string $folder,
        public string $state,
        public bool $isNew,
        public ?int $uid,
    ) {
    }
}
