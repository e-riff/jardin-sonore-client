<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\RepertoireItemRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class DeleteRepertoireItem
{
    public function __construct(private RepertoireItemRepositoryInterface $repertoireItemRepository)
    {
    }

    public function __invoke(Uuid $uuid): void
    {
        $repertoireItem = $this->repertoireItemRepository->findByUuid($uuid);

        if (null === $repertoireItem) {
            throw new InvalidArgumentException('Repertoire item not found.');
        }

        $this->repertoireItemRepository->delete($repertoireItem);
    }
}
