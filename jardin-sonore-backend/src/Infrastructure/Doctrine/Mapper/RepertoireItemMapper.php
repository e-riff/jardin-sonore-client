<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Session\RepertoireBlock;
use App\Domain\Model\Session\RepertoireItem;
use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;

final readonly class RepertoireItemMapper
{
    public function toDomain(RepertoireItemEntity $repertoireItemEntity): RepertoireItem
    {
        return new RepertoireItem(
            type: $repertoireItemEntity->getType(),
            title: $repertoireItemEntity->getTitle(),
            source: $repertoireItemEntity->getSource(),
            body: $repertoireItemEntity->getBody(),
            contentBlocks: array_map(
                static fn (array $contentBlock): RepertoireBlock => RepertoireBlock::fromArray($contentBlock),
                $repertoireItemEntity->getContentBlocks(),
            ),
            notes: $repertoireItemEntity->getNotes(),
            linkedMediaUuids: $repertoireItemEntity->getLinkedMediaUuids(),
            active: $repertoireItemEntity->isActive(),
            createdAt: $repertoireItemEntity->getCreatedAt(),
            updatedAt: $repertoireItemEntity->getUpdatedAt(),
            uuid: $repertoireItemEntity->getUuid(),
        );
    }

    public function toEntity(RepertoireItem $repertoireItem, ?RepertoireItemEntity $repertoireItemEntity = null): RepertoireItemEntity
    {
        $repertoireItemEntity ??= new RepertoireItemEntity();
        $repertoireItemEntity
            ->setUuid($repertoireItem->getUuid())
            ->setType($repertoireItem->getType())
            ->setTitle($repertoireItem->getTitle())
            ->setSource($repertoireItem->getSource())
            ->setBody($repertoireItem->getBody())
            ->setContentBlocks(array_map(
                static fn (RepertoireBlock $contentBlock): array => $contentBlock->toArray(),
                $repertoireItem->getContentBlocks(),
            ))
            ->setNotes($repertoireItem->getNotes())
            ->setLinkedMediaUuids($repertoireItem->getLinkedMediaUuids())
            ->setActive($repertoireItem->isActive())
            ->setCreatedAt($repertoireItem->getCreatedAt())
            ->setUpdatedAt($repertoireItem->getUpdatedAt());

        return $repertoireItemEntity;
    }
}
