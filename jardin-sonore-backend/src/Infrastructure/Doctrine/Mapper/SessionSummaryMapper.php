<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSummary;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;

final readonly class SessionSummaryMapper
{
    public function toDomain(SessionSummaryEntity $sessionSummaryEntity): SessionSummary
    {
        return new SessionSummary(
            title: $sessionSummaryEntity->getTitle(),
            sessionDate: $sessionSummaryEntity->getSessionDate(),
            organizationName: $sessionSummaryEntity->getOrganizationName(),
            theme: $sessionSummaryEntity->getTheme(),
            generalNotes: $sessionSummaryEntity->getGeneralNotes(),
            materialSummary: $sessionSummaryEntity->getMaterialSummary(),
            furtherExploration: $sessionSummaryEntity->getFurtherExploration(),
            instrumentUuids: $sessionSummaryEntity->getInstrumentUuids(),
            sequences: array_map(
                static fn (array $sequence): SessionSequence => SessionSequence::fromArray($sequence),
                $sessionSummaryEntity->getSequences(),
            ),
            createdAt: $sessionSummaryEntity->getCreatedAt(),
            updatedAt: $sessionSummaryEntity->getUpdatedAt(),
            uuid: $sessionSummaryEntity->getUuid(),
        );
    }

    public function toEntity(
        SessionSummary $sessionSummary,
        ?SessionSummaryEntity $sessionSummaryEntity = null,
    ): SessionSummaryEntity {
        $sessionSummaryEntity ??= new SessionSummaryEntity();

        $sessionSummaryEntity
            ->setUuid($sessionSummary->getUuid())
            ->setTitle($sessionSummary->getTitle())
            ->setSessionDate($sessionSummary->getSessionDate())
            ->setOrganizationName($sessionSummary->getOrganizationName())
            ->setTheme($sessionSummary->getTheme())
            ->setGeneralNotes($sessionSummary->getGeneralNotes())
            ->setMaterialSummary($sessionSummary->getMaterialSummary())
            ->setFurtherExploration($sessionSummary->getFurtherExploration())
            ->setInstrumentUuids($sessionSummary->getInstrumentUuids())
            ->setSequences(array_map(
                static fn (SessionSequence $sessionSequence): array => $sessionSequence->toArray(),
                $sessionSummary->getSequences(),
            ))
            ->setCreatedAt($sessionSummary->getCreatedAt())
            ->setUpdatedAt($sessionSummary->getUpdatedAt());

        return $sessionSummaryEntity;
    }
}
