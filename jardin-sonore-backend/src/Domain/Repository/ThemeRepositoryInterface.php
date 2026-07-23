<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\ContentCatalog\Theme;
use Symfony\Component\Uid\Uuid;

interface ThemeRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?Theme;

    /** @return list<Theme> */
    public function findAllOrderedByLabel(): array;

    public function save(Theme $theme): void;
}
