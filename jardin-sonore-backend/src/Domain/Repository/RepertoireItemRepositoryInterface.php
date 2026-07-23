<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use Symfony\Component\Uid\Uuid;

interface RepertoireItemRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?RepertoireItem;

    /** @return list<RepertoireItem> */
    public function search(?RepertoireItemType $repertoireItemType = null, ?string $query = null, bool $activeOnly = false): array;

    public function save(RepertoireItem $repertoireItem): void;

    public function delete(RepertoireItem $repertoireItem): void;
}
