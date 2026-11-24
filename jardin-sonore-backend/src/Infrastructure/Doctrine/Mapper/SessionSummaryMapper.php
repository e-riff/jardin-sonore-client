<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\Organization;
use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSummary;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\SessionFamilyEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class SessionSummaryMapper
{
    public function __construct(
        private OrganizationMapper $organizationMapper,
        private ThemeMapper $themeMapper,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function toDomain(SessionSummaryEntity $sessionSummaryEntity): SessionSummary
    {
        $sequences = [];
        foreach ($sessionSummaryEntity->getSequences() as $index => $sequence) {
            if (!isset($sequence['uuid']) || '' === $sequence['uuid']) {
                $sequence['uuid'] = Uuid::v5($sessionSummaryEntity->getUuid(), "legacy-sequence-{$index}")->toRfc4122();
            }
            $sequences[] = SessionSequence::fromArray($sequence);
        }

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
            sequences: $sequences,
            createdAt: $sessionSummaryEntity->getCreatedAt(),
            updatedAt: $sessionSummaryEntity->getUpdatedAt(),
            uuid: $sessionSummaryEntity->getUuid(),
            documentStatus: $sessionSummaryEntity->getDocumentStatus(),
            documentPath: $sessionSummaryEntity->getDocumentPath(),
            documentError: $sessionSummaryEntity->getDocumentError(),
            themes: array_map(fn (ThemeEntity $themeEntity): \App\Domain\Model\ContentCatalog\Theme => $this->themeMapper->toDomain($themeEntity), $sessionSummaryEntity->getThemes()->toArray()),
            published: $sessionSummaryEntity->isPublished(),
            firstPublishedAt: $sessionSummaryEntity->getFirstPublishedAt(),
            familyUuid: $sessionSummaryEntity->getFamily()?->getUuid(),
        );
    }

    public function toEntity(
        SessionSummary $sessionSummary,
        ?SessionSummaryEntity $sessionSummaryEntity = null,
    ): SessionSummaryEntity {
        $sessionSummaryEntity ??= new SessionSummaryEntity();
        $familyUuid = $sessionSummary->getFamilyUuid();
        $family = null === $familyUuid ? null : $this->entityManager->getRepository(SessionFamilyEntity::class)->findOneBy(['uuid' => $familyUuid]);
        if (null !== $familyUuid && !$family instanceof SessionFamilyEntity) {
            throw new LogicException('Session family not found.');
        }
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
            ->setFamily($family)
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
        $sessionSummaryEntity->setPublished($sessionSummary->isPublished());
        $sessionSummaryEntity->setFirstPublishedAt($sessionSummary->getFirstPublishedAt());
        $sessionSummaryEntity
            ->setDocumentStatus($sessionSummary->getDocumentStatus())
            ->setDocumentPath($sessionSummary->getDocumentPath())
            ->setDocumentError($sessionSummary->getDocumentError());

        return $sessionSummaryEntity;
    }
}
