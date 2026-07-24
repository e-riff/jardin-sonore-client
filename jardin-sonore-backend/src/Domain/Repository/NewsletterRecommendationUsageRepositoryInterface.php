<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Mailing\NewsletterRecommendationUsage;
use Symfony\Component\Uid\Uuid;

interface NewsletterRecommendationUsageRepositoryInterface
{
    public function save(NewsletterRecommendationUsage $newsletterRecommendationUsage): void;

    /**
     * @param list<Uuid> $sourceRecommendationUuids
     *
     * @return array<string, list<NewsletterRecommendationUsage>>
     */
    public function findBySourceRecommendationUuids(array $sourceRecommendationUuids): array;
}
