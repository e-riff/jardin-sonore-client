<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

final readonly class InstrumentCatalogResult
{
    /**
     * @param list<InstrumentCatalogItem> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
