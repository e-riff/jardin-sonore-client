<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Application\ContentCatalog\InstrumentEditView;
use Symfony\Component\Validator\Constraints as Assert;

final class InstrumentFormModel
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\Length(max: 80)]
    public ?string $tuning = null;

    #[Assert\PositiveOrZero]
    public ?int $quantity = null;

    #[Assert\Length(max: 4000)]
    public ?string $notes = null;

    /**
     * @var list<string>
     */
    public array $tagUuids = [];

    public bool $active = true;

    public static function fromEditView(InstrumentEditView $instrumentEditView): self
    {
        $formModel = new self();
        $formModel->name = $instrumentEditView->name;
        $formModel->tuning = $instrumentEditView->tuning;
        $formModel->quantity = $instrumentEditView->quantity;
        $formModel->notes = $instrumentEditView->notes;
        $formModel->tagUuids = $instrumentEditView->tagUuids;
        $formModel->active = $instrumentEditView->active;

        return $formModel;
    }
}
