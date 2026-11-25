<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Domain\Model\Session\SessionFamily;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\SessionFamilyRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final readonly class McpSessionReader
{
    public function __construct(
        private SessionFamilyRepositoryInterface $families,
        private SessionSummaryRepositoryInterface $sessions,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function searchFamilies(?string $query, int $limit): array
    {
        $query = trim($query ?? '');
        if (160 < mb_strlen($query) || 1 > $limit || 20 < $limit) {
            throw new InvalidArgumentException('Invalid family search.');
        }
        $families = array_filter($this->families->listFamilies(), static function (SessionFamily $family) use ($query): bool {
            if ('' === $query || false !== mb_stripos($family->name, $query)) {
                return true;
            }

            return array_any($family->themes, static fn ($theme): bool => false !== mb_stripos($theme->getLabel(), $query));
        });

        return array_map($this->familyView(...), array_slice(array_values($families), 0, $limit));
    }

    /** @return array<string, mixed> */
    public function family(string $uuid): array
    {
        $id = $this->uuid($uuid);
        $family = $this->families->findByUuid($id) ?? throw new InvalidArgumentException('Family not found.');

        return $this->familyView($family) + ['sessions' => array_map($this->sessionView(...), $this->families->sessions($id))];
    }

    /** @return list<array<string, mixed>> */
    public function searchSessions(?string $query, int $limit): array
    {
        if ((null !== $query && 160 < mb_strlen($query)) || 1 > $limit || 20 < $limit) {
            throw new InvalidArgumentException('Invalid session search.');
        }

        return array_map($this->sessionView(...), array_slice($this->sessions->search($query), 0, $limit));
    }

    /** @return array<string, mixed> */
    public function session(string $uuid): array
    {
        $session = $this->sessions->findByUuid($this->uuid($uuid)) ?? throw new InvalidArgumentException('Session not found.');

        return $this->sessionView($session) + [
            'themes' => array_map(static fn ($theme): array => ['uuid' => $theme->getUuid()->toRfc4122(), 'label' => $theme->getLabel()], $session->getThemes()),
            'generalNotes' => $session->getGeneralNotes(),
            'materialSummary' => $session->getMaterialSummary(),
            'furtherExploration' => $session->getFurtherExploration(),
            'instrumentUuids' => $session->getInstrumentUuids(),
            'recommendationUuids' => $session->getRecommendationUuids(),
            'sequences' => array_map(static fn ($sequence): array => $sequence->toArray(), $session->getSequences()),
        ];
    }

    /** @return array<string, mixed> */
    private function familyView(SessionFamily $family): array
    {
        return ['uuid' => $family->uuid->toRfc4122(), 'name' => $family->name, 'themes' => array_map(static fn ($theme): array => ['uuid' => $theme->getUuid()->toRfc4122(), 'label' => $theme->getLabel()], $family->themes), 'url' => $this->urlGenerator->generate('session_family_show', ['uuid' => $family->uuid], UrlGeneratorInterface::ABSOLUTE_URL)];
    }

    /** @return array<string, mixed> */
    private function sessionView(SessionSummary $session): array
    {
        return ['uuid' => $session->getUuid()->toRfc4122(), 'title' => $session->getTitle(), 'date' => $session->getSessionDate()->format('Y-m-d'), 'published' => $session->isPublished(), 'familyUuid' => $session->getFamilyUuid()?->toRfc4122(), 'url' => $this->urlGenerator->generate('session_edit', ['uuid' => $session->getUuid()], UrlGeneratorInterface::ABSOLUTE_URL)];
    }

    private function uuid(string $uuid): Uuid
    {
        if (!Uuid::isValid($uuid)) {
            throw new InvalidArgumentException('Invalid UUID.');
        }

        return Uuid::fromString($uuid);
    }
}
