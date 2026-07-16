<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\SessionSummaryRepositoryInterface;

final readonly class SearchSessionSummaries
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    /**
     * @return list<SessionSummaryView>
     */
    public function __invoke(?string $query = null): array
    {
        return array_map(
            static fn ($sessionSummary): SessionSummaryView => SessionSummaryView::fromDomain($sessionSummary),
            $this->sessionSummaryRepository->search($query),
        );
    }
}
