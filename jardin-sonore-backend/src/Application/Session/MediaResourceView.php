<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\MediaResource;
use App\Domain\Model\Session\MediaResourceType;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class MediaResourceView
{
    /** @param list<array{uuid:string,label:string,color:string}> $themes */
    public function __construct(
        public Uuid $uuid,
        public MediaResourceType $type,
        public string $title,
        public ?string $source,
        public ?string $description,
        public string $primaryUrl,
        public ?string $imageUrl,
        public array $themes,
        public bool $active,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {
    }

    public function thumbnailUrl(): ?string
    {
        return MediaThumbnailUrl::fromUrl($this->primaryUrl, $this->imageUrl);
    }

    public static function fromDomain(MediaResource $mediaResource): self
    {
        return new self(
            uuid: $mediaResource->getUuid(),
            type: $mediaResource->getType(),
            title: $mediaResource->getTitle(),
            source: $mediaResource->getSource(),
            description: $mediaResource->getDescription(),
            primaryUrl: $mediaResource->getPrimaryUrl(),
            imageUrl: $mediaResource->getImageUrl(),
            themes: array_map(static fn ($theme): array => ['uuid' => $theme->getUuid()->toRfc4122(), 'label' => $theme->getLabel(), 'color' => $theme->getColor()], $mediaResource->getThemes()),
            active: $mediaResource->isActive(),
            updatedAt: $mediaResource->getUpdatedAt(),
            id: $mediaResource->getId(),
        );
    }
}
