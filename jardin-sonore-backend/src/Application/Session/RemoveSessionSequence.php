<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\SessionSummaryRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class RemoveSessionSequence
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function __invoke(Uuid $sessionUuid, Uuid $sequenceUuid): void
    {
        $sessionSummary = $this->sessionSummaryRepository->findByUuid($sessionUuid);

        if (null === $sessionSummary) {
            throw new InvalidArgumentException('Session summary not found.');
        }

        $sessionSummary->removeSequence($sequenceUuid);
        $this->sessionSummaryRepository->save($sessionSummary);
    }
}
