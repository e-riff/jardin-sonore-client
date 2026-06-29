<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\NewsletterRecommendation;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;

final readonly class SearchNewsletterRecommendations
{
    public function __construct(
        private NewsletterRecommendationRepositoryInterface $newsletterRecommendationRepository,
    ) {
    }

    /**
     * @return list<NewsletterRecommendation>
     */
    public function __invoke(?string $query = null, bool $activeOnly = false): array
    {
        return $this->newsletterRecommendationRepository->search($query, $activeOnly);
    }
}
