<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionDocumentStatus;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\SessionRecommendationRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class SessionSummaryView
{
    /**
     * @param list<string>                    $instrumentUuids
     * @param list<string>                    $instrumentNames
     * @param list<SessionRecommendationView> $recommendations
     * @param list<string>                    $recommendationUuids
     * @param list<SessionSequenceView>       $sequences
     */
    public function __construct(
        public Uuid $uuid,
        public string $title,
        public DateTimeImmutable $sessionDate,
        public string $organizationName,
        public ?string $theme,
        public ?string $generalNotes,
        public ?string $materialSummary,
        public ?string $furtherExploration,
        public array $instrumentUuids,
        public array $instrumentNames,
        /** @var list<SessionRecommendationView> */
        public array $recommendations,
        /** @var list<string> */
        public array $recommendationUuids,
        public array $sequences,
        public DateTimeImmutable $updatedAt,
        public SessionDocumentStatus $documentStatus,
        public ?string $documentPath,
        public ?string $documentError,
    ) {
    }

    public static function fromDomain(
        SessionSummary $sessionSummary,
        ?RepertoireItemRepositoryInterface $repertoireItemRepository = null,
        ?InstrumentRepositoryInterface $instrumentRepository = null,
        ?SessionRecommendationRepositoryInterface $sessionRecommendationRepository = null,
    ): self {
        return new self(
            uuid: $sessionSummary->getUuid(),
            title: $sessionSummary->getTitle(),
            sessionDate: $sessionSummary->getSessionDate(),
            organizationName: $sessionSummary->getOrganizationName(),
            theme: $sessionSummary->getTheme(),
            generalNotes: $sessionSummary->getGeneralNotes(),
            materialSummary: $sessionSummary->getMaterialSummary(),
            furtherExploration: $sessionSummary->getFurtherExploration(),
            instrumentUuids: $sessionSummary->getInstrumentUuids(),
            instrumentNames: null === $instrumentRepository ? [] : array_values(array_filter(array_map(
                static fn (string $instrumentUuid): ?string => Uuid::isValid($instrumentUuid)
                    ? $instrumentRepository->findByUuid(Uuid::fromString($instrumentUuid))?->getName()
                    : null,
                $sessionSummary->getInstrumentUuids(),
            ))),
            recommendations: null === $sessionRecommendationRepository ? [] : array_values(array_filter(array_map(
                static function (string $recommendationUuid) use ($sessionRecommendationRepository): ?SessionRecommendationView {
                    if (!Uuid::isValid($recommendationUuid)) {
                        return null;
                    }

                    $sessionRecommendation = $sessionRecommendationRepository->findByUuid(Uuid::fromString($recommendationUuid));

                    return null === $sessionRecommendation ? null : SessionRecommendationView::fromDomain($sessionRecommendation);
                },
                $sessionSummary->getRecommendationUuids(),
            ))),
            recommendationUuids: $sessionSummary->getRecommendationUuids(),
            sequences: array_map(
                static fn ($sessionSequence): SessionSequenceView => SessionSequenceView::fromDomain(
                    $sessionSequence,
                    null === $repertoireItemRepository || null === $sessionSequence->sourceUuid
                        ? null
                        : $repertoireItemRepository->findByUuid($sessionSequence->sourceUuid),
                    $instrumentRepository,
                ),
                $sessionSummary->getSequences(),
            ),
            updatedAt: $sessionSummary->getUpdatedAt(),
            documentStatus: $sessionSummary->getDocumentStatus(),
            documentPath: $sessionSummary->getDocumentPath(),
            documentError: $sessionSummary->getDocumentError(),
        );
    }
}
