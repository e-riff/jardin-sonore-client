<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

interface InstrumentCatalogQueryInterface
{
    public function findCatalogItems(InstrumentCatalogCriteria $criteria): InstrumentCatalogResult;
}
