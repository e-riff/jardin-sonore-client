<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Application\Session\RepertoireItemView;
use App\Domain\Model\Session\RepertoireItemType;
use Symfony\Component\Validator\Constraints as Assert;

final class RepertoireItemFormModel
{
    public RepertoireItemType $type = RepertoireItemType::NURSERY_RHYME;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $title = '';

    #[Assert\Length(max: 255)]
    public ?string $source = null;

    public ?string $importText = null;

    /** @var list<RepertoireBlockFormModel> */
    #[Assert\Valid]
    public array $contentBlocks = [];

    public ?string $notes = null;
    /** @var list<string> */
    public array $linkedMediaUuids = [];
    public bool $active = true;

    public static function fromView(RepertoireItemView $repertoireItemView): self
    {
        $formModel = new self();
        $formModel->type = $repertoireItemView->type;
        $formModel->title = $repertoireItemView->title;
        $formModel->source = $repertoireItemView->source;
        $formModel->contentBlocks = array_map(
            static fn ($contentBlock): RepertoireBlockFormModel => RepertoireBlockFormModel::fromView($contentBlock),
            $repertoireItemView->contentBlocks,
        );
        $formModel->notes = $repertoireItemView->notes;
        $formModel->linkedMediaUuids = $repertoireItemView->linkedMediaUuids;
        $formModel->active = $repertoireItemView->active;

        return $formModel;
    }
}
