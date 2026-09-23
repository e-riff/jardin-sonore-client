<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\SessionSummaryRepositoryInterface;

final readonly class RegenerateSessionDocuments
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function __invoke(): int
    {
        $sessionSummaries = $this->sessionSummaryRepository->search();
        foreach ($sessionSummaries as $sessionSummary) {
            $sessionSummary->markDocumentPending();
            $this->sessionSummaryRepository->save($sessionSummary);
        }

        return count($sessionSummaries);
    }
}
