<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Repository\RepertoireItemRepositoryInterface;

final readonly class SearchRepertoireItems
{
    public function __construct(private RepertoireItemRepositoryInterface $repertoireItemRepository)
    {
    }

    /** @return list<RepertoireItemView> */
    public function __invoke(?RepertoireItemType $repertoireItemType = null, ?string $query = null, bool $activeOnly = false): array
    {
        return array_map(
            static fn ($repertoireItem): RepertoireItemView => RepertoireItemView::fromDomain($repertoireItem),
            $this->repertoireItemRepository->search($repertoireItemType, $query, $activeOnly),
        );
    }
}
