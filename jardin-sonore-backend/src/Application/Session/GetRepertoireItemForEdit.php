<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\RepertoireItemRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetRepertoireItemForEdit
{
    public function __construct(private RepertoireItemRepositoryInterface $repertoireItemRepository)
    {
    }

    public function __invoke(Uuid $uuid): ?RepertoireItemView
    {
        $repertoireItem = $this->repertoireItemRepository->findByUuid($uuid);

        return null === $repertoireItem ? null : RepertoireItemView::fromDomain($repertoireItem);
    }
}
