<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\SessionRecommendationRepositoryInterface;

final readonly class SearchSessionRecommendations
{
    public function __construct(private SessionRecommendationRepositoryInterface $sessionRecommendationRepository)
    {
    }

    /** @return list<SessionRecommendationView> */
    public function __invoke(?string $query = null, bool $activeOnly = false): array
    {
        return array_map(
            static fn ($sessionRecommendation): SessionRecommendationView => SessionRecommendationView::fromDomain($sessionRecommendation),
            $this->sessionRecommendationRepository->search($query, $activeOnly),
        );
    }
}
