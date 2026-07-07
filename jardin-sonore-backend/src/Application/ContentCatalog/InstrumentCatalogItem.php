<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class InstrumentCatalogItem
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public Uuid $uuid,
        public string $name,
        public ?string $tuning,
        public ?int $quantity,
        public ?string $notes,
        public bool $active,
        public DateTimeImmutable $updatedAt,
        public array $tags,
    ) {
    }
}
