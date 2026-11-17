<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DuplicateSessionSummary
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function __invoke(Uuid $uuid, string $title): ?SessionSummary
    {
        $sourceSessionSummary = $this->sessionSummaryRepository->findByUuid($uuid);
        if (null === $sourceSessionSummary) {
            return null;
        }
        $sessionSummary = new SessionSummary(
            title: $title,
            sessionDate: $sourceSessionSummary->getSessionDate(),
            theme: $sourceSessionSummary->getTheme(),
            generalNotes: $sourceSessionSummary->getGeneralNotes(),
            materialSummary: $sourceSessionSummary->getMaterialSummary(),
            furtherExploration: $sourceSessionSummary->getFurtherExploration(),
            instrumentUuids: $sourceSessionSummary->getInstrumentUuids(),
            recommendationUuids: $sourceSessionSummary->getRecommendationUuids(),
            sequences: array_map(static function (SessionSequence $sessionSequence): SessionSequence {
                $payload = $sessionSequence->toArray();
                $payload['uuid'] = Uuid::v4()->toRfc4122();

                return SessionSequence::fromArray($payload);
            }, $sourceSessionSummary->getSequences()),
            themes: $sourceSessionSummary->getThemes(),
        );
        $this->sessionSummaryRepository->save($sessionSummary);

        return $sessionSummary;
    }
}
