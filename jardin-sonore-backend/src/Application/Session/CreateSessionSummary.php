<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\SessionSummaryRepositoryInterface;

final readonly class CreateSessionSummary
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function __invoke(SaveSessionSummaryInput $saveSessionSummaryInput): SessionSummary
    {
        $sessionSummary = new SessionSummary(
            title: $saveSessionSummaryInput->title,
            sessionDate: $saveSessionSummaryInput->sessionDate,
            organizations: $saveSessionSummaryInput->organizations,
            theme: $saveSessionSummaryInput->theme,
            generalNotes: $saveSessionSummaryInput->generalNotes,
            materialSummary: $saveSessionSummaryInput->materialSummary,
            furtherExploration: $saveSessionSummaryInput->furtherExploration,
            instrumentUuids: $saveSessionSummaryInput->instrumentUuids,
            recommendationUuids: $saveSessionSummaryInput->recommendationUuids,
        );

        $this->sessionSummaryRepository->save($sessionSummary);

        return $sessionSummary;
    }
}
