<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionRecommendation;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class SessionRecommendationView
{
    public function __construct(
        public Uuid $uuid,
        public string $title,
        public string $text,
        public ?string $notes,
        public ?string $primaryUrl,
        public ?string $secondaryUrl,
        public ?string $imageUrl,
        public bool $active,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromDomain(SessionRecommendation $sessionRecommendation): self
    {
        return new self(
            uuid: $sessionRecommendation->getUuid(),
            title: $sessionRecommendation->getTitle(),
            text: $sessionRecommendation->getText(),
            notes: $sessionRecommendation->getNotes(),
            primaryUrl: $sessionRecommendation->getPrimaryUrl(),
            secondaryUrl: $sessionRecommendation->getSecondaryUrl(),
            imageUrl: $sessionRecommendation->getImageUrl(),
            active: $sessionRecommendation->isActive(),
            updatedAt: $sessionRecommendation->getUpdatedAt(),
        );
    }
}
