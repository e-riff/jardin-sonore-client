<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Application\Session\SessionSummaryView;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final class SessionSummaryFormModel
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $title = '';

    #[Assert\NotNull]
    public ?DateTimeImmutable $sessionDate = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $organizationName = '';

    #[Assert\Length(max: 255)]
    public ?string $theme = null;

    public ?string $generalNotes = null;

    public ?string $materialSummary = null;

    public ?string $furtherExploration = null;

    /**
     * @var list<string>
     */
    public array $instrumentUuids = [];

    public static function fromView(SessionSummaryView $sessionSummaryView): self
    {
        $formModel = new self();
        $formModel->title = $sessionSummaryView->title;
        $formModel->sessionDate = $sessionSummaryView->sessionDate;
        $formModel->organizationName = $sessionSummaryView->organizationName;
        $formModel->theme = $sessionSummaryView->theme;
        $formModel->generalNotes = $sessionSummaryView->generalNotes;
        $formModel->materialSummary = $sessionSummaryView->materialSummary;
        $formModel->furtherExploration = $sessionSummaryView->furtherExploration;
        $formModel->instrumentUuids = $sessionSummaryView->instrumentUuids;

        return $formModel;
    }
}
