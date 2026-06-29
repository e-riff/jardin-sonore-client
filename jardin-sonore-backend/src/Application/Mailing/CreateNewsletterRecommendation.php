<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\RecommendationImageStorageInterface;
use App\Domain\Model\Mailing\NewsletterRecommendation;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;

final readonly class CreateNewsletterRecommendation
{
    public function __construct(
        private NewsletterRecommendationRepositoryInterface $newsletterRecommendationRepository,
        private RecommendationImageStorageInterface $recommendationImageStorage,
    ) {
    }

    public function __invoke(CreateNewsletterRecommendationInput $input): NewsletterRecommendation
    {
        $imagePath = null === $input->imageFile
            ? null
            : $this->recommendationImageStorage->store($input->imageFile);

        $recommendation = new NewsletterRecommendation(
            title: $input->title,
            text: $input->text,
            url: $input->url,
            linkLabel: $input->linkLabel,
            imagePath: $imagePath,
            active: $input->active,
        );

        $this->newsletterRecommendationRepository->save($recommendation);

        return $recommendation;
    }
}
