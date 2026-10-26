<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\ContentCatalog\Theme;
use Symfony\Component\Uid\Uuid;

interface ThemeRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?Theme;

    public function findByLabel(string $label): ?Theme;

    /** @return list<Theme> */
    public function findAllOrderedByLabel(): array;

    public function save(Theme $theme): void;

    /** @return array<string, int> Usage counts keyed by RFC 4122 UUID */
    public function usageCountsByUuid(): array;

    public function delete(Theme $theme): void;
}
