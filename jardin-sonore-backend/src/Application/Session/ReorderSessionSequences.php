<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\SessionSummaryRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class ReorderSessionSequences
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    /**
     * @param list<Uuid> $sequenceUuids
     */
    public function __invoke(Uuid $sessionUuid, array $sequenceUuids): void
    {
        $sessionSummary = $this->sessionSummaryRepository->findByUuid($sessionUuid);

        if (null === $sessionSummary) {
            throw new InvalidArgumentException('Session summary not found.');
        }

        $sessionSummary->reorderSequences($sequenceUuids);
        $this->sessionSummaryRepository->save($sessionSummary);
    }
}
