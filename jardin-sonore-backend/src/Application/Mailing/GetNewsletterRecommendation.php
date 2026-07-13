<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\NewsletterRecommendation;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetNewsletterRecommendation
{
    public function __construct(private NewsletterRecommendationRepositoryInterface $newsletterRecommendationRepository)
    {
    }

    public function __invoke(Uuid $uuid): ?NewsletterRecommendation
    {
        return $this->newsletterRecommendationRepository->findByUuid($uuid);
    }
}
