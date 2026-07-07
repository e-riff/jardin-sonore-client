<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

final readonly class SaveInstrumentInput
{
    /**
     * @param list<string> $tagUuids
     */
    public function __construct(
        public string $name,
        public ?string $tuning,
        public ?int $quantity,
        public ?string $notes,
        public array $tagUuids,
        public bool $active,
    ) {
    }
}
