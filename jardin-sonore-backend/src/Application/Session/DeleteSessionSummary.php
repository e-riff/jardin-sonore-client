<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\SessionSummaryRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DeleteSessionSummary
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function __invoke(Uuid $sessionUuid): void
    {
        $sessionSummary = $this->sessionSummaryRepository->findByUuid($sessionUuid);
        if (null !== $sessionSummary) {
            $this->sessionSummaryRepository->remove($sessionSummary);
        }
    }
}
