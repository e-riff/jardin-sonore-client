<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetNewsletterRecommendationForEdit
{
    public function __construct(private NewsletterRecommendationRepositoryInterface $newsletterRecommendationRepository)
    {
    }

    public function __invoke(Uuid $uuid): ?NewsletterRecommendationView
    {
        $newsletterRecommendation = $this->newsletterRecommendationRepository->findByUuid($uuid);

        return null === $newsletterRecommendation ? null : NewsletterRecommendationView::fromNewsletterRecommendation($newsletterRecommendation);
    }
}
