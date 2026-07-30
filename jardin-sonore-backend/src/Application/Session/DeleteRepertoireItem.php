<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class DeleteRepertoireItem
{
    public function __construct(
        private RepertoireItemRepositoryInterface $repertoireItemRepository,
        private SessionSummaryRepositoryInterface $sessionSummaryRepository,
    ) {
    }

    public function __invoke(Uuid $uuid): void
    {
        $repertoireItem = $this->repertoireItemRepository->findByUuid($uuid);

        if (null === $repertoireItem) {
            throw new InvalidArgumentException('Repertoire item not found.');
        }

        foreach ($this->sessionSummaryRepository->search() as $sessionSummary) {
            foreach ($sessionSummary->getSequences() as $sessionSequence) {
                if ($sessionSequence->sourceUuid?->equals($uuid)) {
                    throw new LogicException('A repertoire item used in a session cannot be deleted.');
                }
            }
        }

        $this->repertoireItemRepository->delete($repertoireItem);
    }
}
