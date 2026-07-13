<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\RecommendationImageStorageInterface;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateNewsletterRecommendation
{
    public function __construct(
        private NewsletterRecommendationRepositoryInterface $newsletterRecommendationRepository,
        private RecommendationImageStorageInterface $recommendationImageStorage,
    ) {
    }

    public function __invoke(
        Uuid $recommendationUuid,
        CreateNewsletterRecommendationInput $input,
    ): void {
        $recommendation = $this->newsletterRecommendationRepository->findByUuid($recommendationUuid);

        if (null === $recommendation) {
            throw new InvalidArgumentException('Newsletter recommendation not found.');
        }

        $imagePath = null === $input->imageFile
            ? $recommendation->getImagePath()
            : $this->recommendationImageStorage->store($input->imageFile);

        $recommendation->updateContent(
            title: $input->title,
            tag: $input->tag,
            text: $input->text,
            url: $input->url,
            linkLabel: $input->linkLabel,
            imagePath: $imagePath,
        );

        if ($input->active) {
            $recommendation->activate();
        } else {
            $recommendation->deactivate();
        }

        $this->newsletterRecommendationRepository->save($recommendation);
    }
}
