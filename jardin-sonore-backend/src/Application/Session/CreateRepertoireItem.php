<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\RepertoireBlock;
use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Repository\RepertoireItemRepositoryInterface;

final readonly class CreateRepertoireItem
{
    public function __construct(private RepertoireItemRepositoryInterface $repertoireItemRepository)
    {
    }

    public function __invoke(SaveRepertoireItemInput $saveRepertoireItemInput): RepertoireItem
    {
        $repertoireItem = new RepertoireItem(
            type: $saveRepertoireItemInput->type,
            title: $saveRepertoireItemInput->title,
            source: $saveRepertoireItemInput->source,
            body: $saveRepertoireItemInput->body,
            contentBlocks: array_map(
                static fn (SaveRepertoireBlockInput $contentBlock): RepertoireBlock => new RepertoireBlock(
                    kind: $contentBlock->kind,
                    text: $contentBlock->text,
                    gesture: $contentBlock->gesture,
                ),
                $saveRepertoireItemInput->contentBlocks,
            ),
            notes: $saveRepertoireItemInput->notes,
            linkedMediaUuids: $saveRepertoireItemInput->linkedMediaUuids,
            active: $saveRepertoireItemInput->active,
        );

        $this->repertoireItemRepository->save($repertoireItem);

        return $repertoireItem;
    }
}
