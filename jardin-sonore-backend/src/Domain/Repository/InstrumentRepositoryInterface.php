<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\ContentCatalog\Instrument;
use Symfony\Component\Uid\Uuid;

interface InstrumentRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?Instrument;

    public function save(Instrument $instrument): void;
}
