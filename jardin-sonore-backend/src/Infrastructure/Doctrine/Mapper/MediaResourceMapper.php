<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Session\MediaResource;
use App\Infrastructure\Doctrine\Entity\MediaResourceEntity;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;

final readonly class MediaResourceMapper
{
    public function __construct(private ThemeMapper $themeMapper)
    {
    }

    public function toDomain(MediaResourceEntity $mediaResourceEntity): MediaResource
    {
        return new MediaResource(
            type: $mediaResourceEntity->getType(),
            title: $mediaResourceEntity->getTitle(),
            primaryUrl: $mediaResourceEntity->getPrimaryUrl(),
            source: $mediaResourceEntity->getSource(),
            description: $mediaResourceEntity->getDescription(),
            secondaryUrl: $mediaResourceEntity->getSecondaryUrl(),
            imageUrl: $mediaResourceEntity->getImageUrl(),
            active: $mediaResourceEntity->isActive(),
            createdAt: $mediaResourceEntity->getCreatedAt(),
            updatedAt: $mediaResourceEntity->getUpdatedAt(),
            uuid: $mediaResourceEntity->getUuid(),
            themes: array_map(fn (ThemeEntity $theme): \App\Domain\Model\ContentCatalog\Theme => $this->themeMapper->toDomain($theme), $mediaResourceEntity->getThemes()->toArray()),
        );
    }

    public function toEntity(MediaResource $mediaResource, ?MediaResourceEntity $mediaResourceEntity = null): MediaResourceEntity
    {
        $mediaResourceEntity ??= new MediaResourceEntity();
        $mediaResourceEntity
            ->setUuid($mediaResource->getUuid())
            ->setType($mediaResource->getType())
            ->setTitle($mediaResource->getTitle())
            ->setPrimaryUrl($mediaResource->getPrimaryUrl())
            ->setSource($mediaResource->getSource())
            ->setDescription($mediaResource->getDescription())
            ->setSecondaryUrl($mediaResource->getSecondaryUrl())
            ->setImageUrl($mediaResource->getImageUrl())
            ->setActive($mediaResource->isActive())
            ->setCreatedAt($mediaResource->getCreatedAt())
            ->setUpdatedAt($mediaResource->getUpdatedAt());

        return $mediaResourceEntity;
    }
}
