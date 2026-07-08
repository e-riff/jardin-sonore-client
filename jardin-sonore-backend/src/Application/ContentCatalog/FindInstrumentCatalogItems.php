<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

final readonly class FindInstrumentCatalogItems
{
    public function __construct(private InstrumentCatalogQueryInterface $instrumentCatalogQuery)
    {
    }

    public function __invoke(InstrumentCatalogCriteria $criteria): InstrumentCatalogResult
    {
        return $this->instrumentCatalogQuery->findCatalogItems($criteria);
    }
}
