<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\SessionSummaryRepositoryInterface;
use App\Domain\Repository\ThemeRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateSessionSummary
{
    public function __construct(
        private SessionSummaryRepositoryInterface $sessionSummaryRepository,
        private ThemeRepositoryInterface $themeRepository,
    ) {
    }

    public function __invoke(Uuid $uuid, SaveSessionSummaryInput $saveSessionSummaryInput): void
    {
        $sessionSummary = $this->sessionSummaryRepository->findByUuid($uuid);

        if (null === $sessionSummary) {
            throw new InvalidArgumentException('Session summary not found.');
        }

        $sessionSummary->updateDetails(
            title: $saveSessionSummaryInput->title,
            sessionDate: $saveSessionSummaryInput->sessionDate,
            organizations: $saveSessionSummaryInput->organizations,
            theme: $saveSessionSummaryInput->theme,
            generalNotes: $saveSessionSummaryInput->generalNotes,
            materialSummary: $saveSessionSummaryInput->materialSummary,
            furtherExploration: $saveSessionSummaryInput->furtherExploration,
            instrumentUuids: $saveSessionSummaryInput->instrumentUuids,
            recommendationUuids: $saveSessionSummaryInput->recommendationUuids,
            themes: $this->resolveThemes($saveSessionSummaryInput->themeUuids),
        );

        $this->sessionSummaryRepository->save($sessionSummary);
    }

    /** @return list<\App\Domain\Model\ContentCatalog\Theme> */
    private function resolveThemes(array $themeUuids): array
    {
        return array_values(array_filter(array_map(
            fn (string $themeUuid) => Uuid::isValid($themeUuid) ? $this->themeRepository->findByUuid(Uuid::fromString($themeUuid)) : null,
            array_unique($themeUuids),
        )));
    }
}
