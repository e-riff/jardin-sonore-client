<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\RepertoireBlock;
use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\ThemeRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CreateRepertoireItem
{
    public function __construct(private RepertoireItemRepositoryInterface $repertoireItemRepository, private ThemeRepositoryInterface $themeRepository)
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
            themes: $this->resolveThemes($saveRepertoireItemInput->themeUuids),
        );

        $this->repertoireItemRepository->save($repertoireItem);

        return $repertoireItem;
    }

    /**
     * @param list<string> $themeUuids
     *
     * @return list<\App\Domain\Model\ContentCatalog\Theme>
     */
    private function resolveThemes(array $themeUuids): array
    {
        return array_values(array_filter(array_map(fn (string $uuid) => Uuid::isValid($uuid) ? $this->themeRepository->findByUuid(Uuid::fromString($uuid)) : null, array_unique($themeUuids))));
    }
}
