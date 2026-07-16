<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\SessionSummaryRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetSessionSummary
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function __invoke(Uuid $uuid): ?SessionSummaryView
    {
        $sessionSummary = $this->sessionSummaryRepository->findByUuid($uuid);

        return null === $sessionSummary ? null : SessionSummaryView::fromDomain($sessionSummary);
    }
}
