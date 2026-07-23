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

    #[Assert\Length(max: 255)]
    public ?string $subtitle = null;

    public ?string $generalNotes = null;

    /**
     * @var list<string>
     */
    public array $instrumentUuids = [];

    public static function fromView(SessionSummaryView $sessionSummaryView): self
    {
        $formModel = new self();
        $formModel->title = $sessionSummaryView->title;
        $formModel->sessionDate = $sessionSummaryView->sessionDate;
        $formModel->subtitle = $sessionSummaryView->theme;
        $formModel->generalNotes = $sessionSummaryView->generalNotes;
        $formModel->instrumentUuids = $sessionSummaryView->instrumentUuids;

        return $formModel;
    }
}
