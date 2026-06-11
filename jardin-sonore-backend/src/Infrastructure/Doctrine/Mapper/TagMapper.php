<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\Tag;
use App\Infrastructure\Doctrine\Entity\TagEntity;

final readonly class TagMapper
{
    public function toDomain(TagEntity $tagEntity): Tag
    {
        return new Tag(
            label: $tagEntity->getLabel(),
            uuid: $tagEntity->getUuid(),
            id: $tagEntity->getId(),
        );
    }

    public function toEntity(Tag $tag, ?TagEntity $tagEntity = null): TagEntity
    {
        $tagEntity ??= new TagEntity();

        $tagEntity
            ->setUuid($tag->getUuid())
            ->setLabel($tag->getLabel());

        return $tagEntity;
    }
}
