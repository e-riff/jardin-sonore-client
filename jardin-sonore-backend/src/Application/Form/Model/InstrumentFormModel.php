<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Domain\Model\ContentCatalog\Instrument;
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

    public static function fromInstrument(Instrument $instrument): self
    {
        $formModel = new self();
        $formModel->name = $instrument->getName();
        $formModel->tuning = $instrument->getTuning();
        $formModel->quantity = $instrument->getQuantity();
        $formModel->notes = $instrument->getNotes();
        $formModel->tagUuids = array_map(
            static fn ($instrumentTag): string => $instrumentTag->getUuid()->toRfc4122(),
            $instrument->getTags(),
        );
        $formModel->active = $instrument->isActive();

        return $formModel;
    }
}
