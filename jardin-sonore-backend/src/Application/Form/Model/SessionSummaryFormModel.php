<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Application\Session\SessionSummaryView;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final class SessionSummaryFormModel
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $title = '';

    #[Assert\NotNull]
    public ?DateTimeImmutable $sessionDate = null;

    #[Assert\Length(max: 255)]
    public ?string $subtitle = null;

    public bool $published = false;

    /** @var list<string> */
    public array $themeUuids = [];

    /** @var list<OrganizationEntity> */
    public array $organizations = [];

    public ?string $generalNotes = null;

    /**
     * @var list<string>
     */
    public array $instrumentUuids = [];

    /** @var list<string> */
    public array $recommendationUuids = [];

    public ?string $recommendationOrder = '';

    public static function fromView(SessionSummaryView $sessionSummaryView): self
    {
        $formModel = new self();
        $formModel->title = $sessionSummaryView->title;
        $formModel->sessionDate = $sessionSummaryView->sessionDate;
        $formModel->subtitle = $sessionSummaryView->theme;
        $formModel->published = $sessionSummaryView->published;
        $formModel->themeUuids = array_column($sessionSummaryView->themes, 'uuid');
        $formModel->generalNotes = $sessionSummaryView->generalNotes;
        $formModel->instrumentUuids = $sessionSummaryView->instrumentUuids;
        $formModel->recommendationUuids = $sessionSummaryView->recommendationUuids;
        $formModel->recommendationOrder = implode(',', $sessionSummaryView->recommendationUuids);

        return $formModel;
    }

    /** @return list<string> */
    public function orderedRecommendationUuids(): array
    {
        $selectedRecommendationUuids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $uuid): string => is_string($uuid) ? trim($uuid) : '', $this->recommendationUuids),
            static fn (string $uuid): bool => '' !== $uuid,
        )));
        $selectedRecommendationUuidsByValue = array_fill_keys($selectedRecommendationUuids, true);
        $orderedRecommendationUuids = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $this->recommendationOrder ?? '')),
            static fn (string $uuid): bool => isset($selectedRecommendationUuidsByValue[$uuid]),
        )));

        foreach ($selectedRecommendationUuids as $selectedRecommendationUuid) {
            if (!in_array($selectedRecommendationUuid, $orderedRecommendationUuids, true)) {
                $orderedRecommendationUuids[] = $selectedRecommendationUuid;
            }
        }

        return $orderedRecommendationUuids;
    }
}
