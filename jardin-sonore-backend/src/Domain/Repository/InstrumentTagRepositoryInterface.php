<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\ContentCatalog\InstrumentTag;
use Symfony\Component\Uid\Uuid;

interface InstrumentTagRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?InstrumentTag;

    public function findByLabel(string $label): ?InstrumentTag;

    /**
     * @param list<string> $uuids
     *
     * @return list<InstrumentTag>
     */
    public function findByUuids(array $uuids): array;

    /**
     * @return list<InstrumentTag>
     */
    public function findAllOrderedByLabel(): array;

    public function save(InstrumentTag $instrumentTag): void;
}
