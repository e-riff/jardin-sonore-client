<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\ContentCatalog\Instrument;
use Symfony\Component\Uid\Uuid;

interface InstrumentRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?Instrument;

    /**
     * @return list<Instrument>
     */
    public function findAllOrderedByName(): array;

    public function save(Instrument $instrument): void;

    public function delete(Instrument $instrument): void;
}
