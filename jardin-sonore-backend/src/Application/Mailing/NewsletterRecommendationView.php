<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\NewsletterRecommendation;
use App\Domain\Model\Mailing\NewsletterRecommendationUsage;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class NewsletterRecommendationView
{
    public function __construct(
        public Uuid $uuid,
        public string $title,
        public string $text,
        public ?string $tag,
        public ?string $url,
        public ?string $linkLabel,
        public ?string $imagePath,
        public bool $active,
        public DateTimeImmutable $updatedAt,
        /** @var list<NewsletterRecommendationUsage> */
        public array $usages = [],
    ) {
    }

    /** @param list<NewsletterRecommendationUsage> $usages */
    public static function fromNewsletterRecommendation(NewsletterRecommendation $newsletterRecommendation, array $usages = []): self
    {
        return new self(
            uuid: $newsletterRecommendation->getUuid(),
            title: $newsletterRecommendation->getTitle(),
            text: $newsletterRecommendation->getText(),
            tag: $newsletterRecommendation->getTag(),
            url: $newsletterRecommendation->getUrl(),
            linkLabel: $newsletterRecommendation->getLinkLabel(),
            imagePath: $newsletterRecommendation->getImagePath(),
            active: $newsletterRecommendation->isActive(),
            updatedAt: $newsletterRecommendation->getUpdatedAt(),
            usages: $usages,
        );
    }
}
