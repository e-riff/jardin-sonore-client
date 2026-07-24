<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use App\Domain\Repository\NewsletterRecommendationUsageRepositoryInterface;

final readonly class SearchNewsletterRecommendations
{
    public function __construct(
        private NewsletterRecommendationRepositoryInterface $newsletterRecommendationRepository,
        private NewsletterRecommendationUsageRepositoryInterface $newsletterRecommendationUsageRepository,
    ) {
    }

    /**
     * @return list<NewsletterRecommendationView>
     */
    public function __invoke(?string $query = null, bool $activeOnly = false): array
    {
        $newsletterRecommendations = $this->newsletterRecommendationRepository->search($query, $activeOnly);
        $usagesBySourceRecommendationUuid = $this->newsletterRecommendationUsageRepository->findBySourceRecommendationUuids(array_map(
            static fn ($newsletterRecommendation) => $newsletterRecommendation->getUuid(),
            $newsletterRecommendations,
        ));

        return array_map(
            static fn ($newsletterRecommendation): NewsletterRecommendationView => NewsletterRecommendationView::fromNewsletterRecommendation(
                $newsletterRecommendation,
                $usagesBySourceRecommendationUuid[$newsletterRecommendation->getUuid()->toRfc4122()] ?? [],
            ),
            $newsletterRecommendations,
        );
    }
}
