<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;

final readonly class PortalSessionResponse
{
    /**
     * @param list<array{uuid: string, name: string}> $organizations
     * @param list<string>                            $instrumentUuids
     * @param list<string>                            $recommendationUuids
     * @param list<array<string, mixed>>              $sequences
     */
    private function __construct(
        public string $uuid,
        public string $title,
        public string $sessionDate,
        public ?string $sharedAt,
        public array $organizations,
        public ?string $theme,
        public ?string $generalNotes,
        public ?string $materialSummary,
        public ?string $furtherExploration,
        public array $instrumentUuids,
        public array $recommendationUuids,
        public array $sequences,
        public string $documentStatus,
    ) {
    }

    /**
     * @param list<OrganizationEntity>        $authorizedOrganizationEntities
     * @param list<array<string, mixed>>|null $sequences
     */
    public static function fromEntity(SessionSummaryEntity $sessionSummaryEntity, array $authorizedOrganizationEntities, ?array $sequences = null): self
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
            $sessionSummaryEntity->getUuid()->toRfc4122(),
            $sessionSummaryEntity->getTitle(),
            $sessionSummaryEntity->getSessionDate()->format('Y-m-d'),
            $sharedAt,
            $organizations,
            $sessionSummaryEntity->getTheme(),
            $sessionSummaryEntity->getGeneralNotes(),
            $sessionSummaryEntity->getMaterialSummary(),
            $sessionSummaryEntity->getFurtherExploration(),
            $sessionSummaryEntity->getInstrumentUuids(),
            $sessionSummaryEntity->getRecommendationUuids(),
            $sequences ?? $sessionSummaryEntity->getSequences(),
            $sessionSummaryEntity->getDocumentStatus()->value,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(bool $withDetails = true): array
    {
        $response = [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'sessionDate' => $this->sessionDate,
            'sharedAt' => $this->sharedAt,
            'organizations' => $this->organizations,
            'theme' => $this->theme,
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
            'recommendationUuids' => $this->recommendationUuids,
            'sequences' => $this->sequences,
        ];
    }
}
