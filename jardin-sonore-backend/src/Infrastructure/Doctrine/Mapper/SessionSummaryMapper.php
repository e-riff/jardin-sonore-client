<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Model\AddressBook\Organization;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

final readonly class SessionSummaryMapper
{
    public function __construct(private OrganizationMapper $organizationMapper, private EntityManagerInterface $entityManager)
    {
    }

    public function toDomain(SessionSummaryEntity $sessionSummaryEntity): SessionSummary
    {
        return new SessionSummary(
            title: $sessionSummaryEntity->getTitle(),
            sessionDate: $sessionSummaryEntity->getSessionDate(),
            organizations: array_values($sessionSummaryEntity->getOrganizations()->map(
                fn (OrganizationEntity $organizationEntity): Organization => $this->organizationMapper->toDomain($organizationEntity),
            )->toArray()),
            theme: $sessionSummaryEntity->getTheme(),
            generalNotes: $sessionSummaryEntity->getGeneralNotes(),
            materialSummary: $sessionSummaryEntity->getMaterialSummary(),
            furtherExploration: $sessionSummaryEntity->getFurtherExploration(),
            instrumentUuids: $sessionSummaryEntity->getInstrumentUuids(),
            recommendationUuids: $sessionSummaryEntity->getRecommendationUuids(),
            sequences: array_map(
                static fn (array $sequence): SessionSequence => SessionSequence::fromArray($sequence),
                $sessionSummaryEntity->getSequences(),
            ),
            createdAt: $sessionSummaryEntity->getCreatedAt(),
            updatedAt: $sessionSummaryEntity->getUpdatedAt(),
            uuid: $sessionSummaryEntity->getUuid(),
            documentStatus: $sessionSummaryEntity->getDocumentStatus(),
            documentPath: $sessionSummaryEntity->getDocumentPath(),
            documentError: $sessionSummaryEntity->getDocumentError(),
        );
    }

    public function toEntity(
        SessionSummary $sessionSummary,
        ?SessionSummaryEntity $sessionSummaryEntity = null,
    ): SessionSummaryEntity {
        $sessionSummaryEntity ??= new SessionSummaryEntity();
        $organizationEntities = [];
        foreach ($sessionSummary->getOrganizations() as $organization) {
            $organizationId = $organization->getId();
            if (null === $organizationId) {
                throw new LogicException('Session organization must be persisted before it can be associated.');
            }
            $organizationEntities[] = $this->entityManager->getReference(OrganizationEntity::class, $organizationId);
        }

        $sessionSummaryEntity
            ->setUuid($sessionSummary->getUuid())
            ->setTitle($sessionSummary->getTitle())
            ->setSessionDate($sessionSummary->getSessionDate())
            ->replaceOrganizations($organizationEntities)
            ->setTheme($sessionSummary->getTheme())
            ->setGeneralNotes($sessionSummary->getGeneralNotes())
            ->setMaterialSummary($sessionSummary->getMaterialSummary())
            ->setFurtherExploration($sessionSummary->getFurtherExploration())
            ->setInstrumentUuids($sessionSummary->getInstrumentUuids())
            ->setRecommendationUuids($sessionSummary->getRecommendationUuids())
            ->setSequences(array_map(
                static fn (SessionSequence $sessionSequence): array => $sessionSequence->toArray(),
                $sessionSummary->getSequences(),
            ))
            ->setCreatedAt($sessionSummary->getCreatedAt())
            ->setUpdatedAt($sessionSummary->getUpdatedAt());
        $sessionSummaryEntity
            ->setDocumentStatus($sessionSummary->getDocumentStatus())
            ->setDocumentPath($sessionSummary->getDocumentPath())
            ->setDocumentError($sessionSummary->getDocumentError());

        return $sessionSummaryEntity;
    }
}
