<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class RepertoireItemView
{
    /**
     * @param list<RepertoireBlockView>                          $contentBlocks
     * @param list<string>                                       $linkedMediaUuids
     * @param list<array{uuid:string,label:string,color:string}> $themes
     */
    public function __construct(
        public Uuid $uuid,
        public RepertoireItemType $type,
        public string $title,
        public ?string $source,
        public string $body,
        public array $contentBlocks,
        public ?string $lyrics,
        public ?string $gestures,
        public ?string $notes,
        public array $linkedMediaUuids,
        public array $themes,
        public bool $active,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromDomain(RepertoireItem $repertoireItem): self
    {
        return new self(
            uuid: $repertoireItem->getUuid(),
            type: $repertoireItem->getType(),
            title: $repertoireItem->getTitle(),
            source: $repertoireItem->getSource(),
            body: $repertoireItem->getBody(),
            contentBlocks: array_map(
                static fn ($contentBlock): RepertoireBlockView => RepertoireBlockView::fromDomain($contentBlock),
                $repertoireItem->getContentBlocks(),
            ),
            lyrics: $repertoireItem->getLyrics(),
            gestures: $repertoireItem->getGestures(),
            notes: $repertoireItem->getNotes(),
            linkedMediaUuids: $repertoireItem->getLinkedMediaUuids(),
            themes: array_map(static fn ($theme): array => ['uuid' => $theme->getUuid()->toRfc4122(), 'label' => $theme->getLabel(), 'color' => $theme->getColor()], $repertoireItem->getThemes()),
            active: $repertoireItem->isActive(),
            updatedAt: $repertoireItem->getUpdatedAt(),
        );
    }
}
