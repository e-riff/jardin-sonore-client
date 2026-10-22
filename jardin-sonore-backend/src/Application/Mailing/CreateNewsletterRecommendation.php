<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\RecommendationImageStorageInterface;
use App\Domain\Model\Mailing\NewsletterRecommendation;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use Throwable;

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
            tag: $input->tag,
            text: $input->text,
            url: $input->url,
            linkLabel: $input->linkLabel,
            imagePath: $imagePath,
            active: $input->active,
        );

        try {
            $this->newsletterRecommendationRepository->save($recommendation);
        } catch (Throwable $exception) {
            if (null !== $imagePath) {
                $this->recommendationImageStorage->delete($imagePath);
            }
            throw $exception;
        }

        return $recommendation;
    }
}
