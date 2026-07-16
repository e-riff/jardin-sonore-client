<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSummary;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class SessionSummaryView
{
    /**
     * @param list<string>              $instrumentUuids
     * @param list<SessionSequenceView> $sequences
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
        public array $sequences,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromDomain(SessionSummary $sessionSummary): self
    {
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
            sequences: array_map(
                static fn ($sessionSequence): SessionSequenceView => SessionSequenceView::fromDomain($sessionSequence),
                $sessionSummary->getSequences(),
            ),
            updatedAt: $sessionSummary->getUpdatedAt(),
        );
    }
}
