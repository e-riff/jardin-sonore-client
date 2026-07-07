<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

final readonly class InstrumentCatalogCriteria
{
    /**
     * @param list<string> $tagUuids
     */
    public function __construct(
        public string $query = '',
        public array $tagUuids = [],
        public string $activeFilter = '',
        public string $quantityFilter = '',
        public string $tuningFilter = '',
        public string $sortBy = 'name',
        public string $sortDirection = 'asc',
        public int $page = 1,
        public int $perPage = 15,
    ) {
    }
}
