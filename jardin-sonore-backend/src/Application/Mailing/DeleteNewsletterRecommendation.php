<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class DeleteNewsletterRecommendation
{
    public function __construct(private NewsletterRecommendationRepositoryInterface $newsletterRecommendationRepository)
    {
    }

    public function __invoke(Uuid $uuid): void
    {
        $newsletterRecommendation = $this->newsletterRecommendationRepository->findByUuid($uuid);

        if (null === $newsletterRecommendation) {
            throw new InvalidArgumentException('Newsletter recommendation not found.');
        }

        $this->newsletterRecommendationRepository->delete($newsletterRecommendation);
    }
}
