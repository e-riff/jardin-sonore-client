<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;

final readonly class SearchNewsletterRecommendations
{
    public function __construct(
        private NewsletterRecommendationRepositoryInterface $newsletterRecommendationRepository,
    ) {
    }

    /**
     * @return list<NewsletterRecommendationView>
     */
    public function __invoke(?string $query = null, bool $activeOnly = false): array
    {
        return array_map(
            static fn ($newsletterRecommendation): NewsletterRecommendationView => NewsletterRecommendationView::fromNewsletterRecommendation($newsletterRecommendation),
            $this->newsletterRecommendationRepository->search($query, $activeOnly),
        );
    }
}
