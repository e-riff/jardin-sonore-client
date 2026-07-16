<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\MediaResource;
use App\Domain\Model\Session\MediaResourceType;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class MediaResourceView
{
    public function __construct(
        public Uuid $uuid,
        public MediaResourceType $type,
        public string $title,
        public ?string $source,
        public ?string $description,
        public string $primaryUrl,
        public ?string $secondaryUrl,
        public ?string $imageUrl,
        public bool $active,
        public DateTimeImmutable $updatedAt,
    ) {
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
            secondaryUrl: $mediaResource->getSecondaryUrl(),
            imageUrl: $mediaResource->getImageUrl(),
            active: $mediaResource->isActive(),
            updatedAt: $mediaResource->getUpdatedAt(),
        );
    }
}
