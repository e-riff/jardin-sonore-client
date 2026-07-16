<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\SessionRecommendationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetSessionRecommendationForEdit
{
    public function __construct(private SessionRecommendationRepositoryInterface $sessionRecommendationRepository)
    {
    }

    public function __invoke(Uuid $uuid): ?SessionRecommendationView
    {
        $sessionRecommendation = $this->sessionRecommendationRepository->findByUuid($uuid);

        return null === $sessionRecommendation ? null : SessionRecommendationView::fromDomain($sessionRecommendation);
    }
}
