<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class NewsletterRecommendationUsage
{
    public function __construct(
        public Uuid $sourceRecommendationUuid,
        public Uuid $campaignUuid,
        public string $campaignTitle,
        public DateTimeImmutable $sentAt,
    ) {
    }
}
