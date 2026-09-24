<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;

final readonly class PortalSessionResponse
{
    /**
     * @param list<array{uuid: string, name: string}>                 $organizations
     * @param list<array{uuid: string, label: string, color: string}> $themes
     * @param list<string>                                            $instrumentUuids
     * @param list<string>                                            $instrumentNames
     * @param list<string>                                            $recommendationUuids
     * @param list<array<string, mixed>>                              $sequences
     */
    private function __construct(
        public string $slug,
        public string $title,
        public string $sessionDate,
        public ?string $sharedAt,
        public array $organizations,
        public array $themes,
        public ?string $subtitle,
        public ?string $generalNotes,
        public ?string $materialSummary,
        public ?string $furtherExploration,
        public array $instrumentUuids,
        public array $instrumentNames,
        public array $recommendationUuids,
        public array $sequences,
        public string $documentStatus,
    ) {
    }

    /**
     * @param list<OrganizationEntity>        $authorizedOrganizationEntities
     * @param list<array<string, mixed>>|null $sequences
     * @param list<string>|null               $instrumentNames
     */
    public static function fromEntity(SessionSummaryEntity $sessionSummaryEntity, array $authorizedOrganizationEntities, ?array $sequences = null, ?array $instrumentNames = null): self
    {
        $authorizedOrganizationUuids = array_flip(array_map(static fn (OrganizationEntity $organizationEntity): string => $organizationEntity->getUuid()->toRfc4122(), $authorizedOrganizationEntities));
        $organizations = [];
        $sharedAt = null;
        foreach ($sessionSummaryEntity->getOrganizationShares() as $organizationShare) {
            $organizationEntity = $organizationShare->getOrganization();
            $uuid = $organizationEntity->getUuid()->toRfc4122();
            if (isset($authorizedOrganizationUuids[$uuid])) {
                $organizations[] = ['uuid' => $uuid, 'name' => $organizationEntity->getName()];
                $organizationSharedAt = $organizationShare->getSharedAt()->format('Y-m-d');
                if (null === $sharedAt || $organizationSharedAt > $sharedAt) {
                    $sharedAt = $organizationSharedAt;
                }
            }
        }
        usort($organizations, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);

        return new self(
            $sessionSummaryEntity->getSlug(),
            $sessionSummaryEntity->getTitle(),
            $sessionSummaryEntity->getSessionDate()->format('Y-m-d'),
            $sharedAt,
            $organizations,
            array_map(static fn (ThemeEntity $themeEntity): array => [
                'uuid' => $themeEntity->getUuid()->toRfc4122(),
                'label' => $themeEntity->getLabel(),
                'color' => $themeEntity->getColor(),
            ], $sessionSummaryEntity->getThemes()->toArray()),
            $sessionSummaryEntity->getTheme(),
            $sessionSummaryEntity->getGeneralNotes(),
            $sessionSummaryEntity->getMaterialSummary(),
            $sessionSummaryEntity->getFurtherExploration(),
            $sessionSummaryEntity->getInstrumentUuids(),
            $instrumentNames ?? [],
            $sessionSummaryEntity->getRecommendationUuids(),
            $sequences ?? $sessionSummaryEntity->getSequences(),
            $sessionSummaryEntity->getDocumentStatus()->value,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(bool $withDetails = true): array
    {
        $response = [
            'slug' => $this->slug,
            'title' => $this->title,
            'sessionDate' => $this->sessionDate,
            'sharedAt' => $this->sharedAt,
            'organizations' => $this->organizations,
            'themes' => $this->themes,
            'subtitle' => $this->subtitle,
            'documentStatus' => $this->documentStatus,
        ];

        if (!$withDetails) {
            return $response;
        }

        return $response + [
            'generalNotes' => $this->generalNotes,
            'materialSummary' => $this->materialSummary,
            'furtherExploration' => $this->furtherExploration,
            'instrumentUuids' => $this->instrumentUuids,
            'instrumentNames' => $this->instrumentNames,
            'recommendationUuids' => $this->recommendationUuids,
            'sequences' => $this->sequences,
        ];
    }
}
