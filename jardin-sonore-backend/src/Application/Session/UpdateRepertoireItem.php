<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\RepertoireBlock;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\ThemeRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateRepertoireItem
{
    public function __construct(private RepertoireItemRepositoryInterface $repertoireItemRepository, private ThemeRepositoryInterface $themeRepository)
    {
    }

    public function __invoke(Uuid $uuid, SaveRepertoireItemInput $saveRepertoireItemInput): void
    {
        $repertoireItem = $this->repertoireItemRepository->findByUuid($uuid);

        if (null === $repertoireItem) {
            throw new InvalidArgumentException('Repertoire item not found.');
        }

        $repertoireItem->setType($saveRepertoireItemInput->type);
        $repertoireItem->updateContent(
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
        );
        $repertoireItem->setActive($saveRepertoireItemInput->active);
        $repertoireItem->setThemes(array_values(array_filter(array_map(fn (string $themeUuid) => Uuid::isValid($themeUuid) ? $this->themeRepository->findByUuid(Uuid::fromString($themeUuid)) : null, array_unique($saveRepertoireItemInput->themeUuids)))));

        $this->repertoireItemRepository->save($repertoireItem);
    }
}
