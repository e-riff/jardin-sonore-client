<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Application\Storage\SessionRecommendationImageStorageInterface;
use App\Domain\Repository\SessionRecommendationRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateSessionRecommendation
{
    public function __construct(
        private SessionRecommendationRepositoryInterface $sessionRecommendationRepository,
        private SessionRecommendationImageStorageInterface $sessionRecommendationImageStorage,
    ) {
    }

    public function __invoke(Uuid $uuid, SaveSessionRecommendationInput $saveSessionRecommendationInput): void
    {
        $sessionRecommendation = $this->sessionRecommendationRepository->findByUuid($uuid);

        if (null === $sessionRecommendation) {
            throw new InvalidArgumentException('Session recommendation not found.');
        }

        $imageUrl = null === $saveSessionRecommendationInput->imageFile
            ? $saveSessionRecommendationInput->imageUrl
            : $this->sessionRecommendationImageStorage->store($saveSessionRecommendationInput->imageFile);

        $sessionRecommendation->updateContent(
            title: $saveSessionRecommendationInput->title,
            text: $saveSessionRecommendationInput->text,
            notes: $saveSessionRecommendationInput->notes,
            primaryUrl: $saveSessionRecommendationInput->primaryUrl,
            secondaryUrl: $saveSessionRecommendationInput->secondaryUrl,
            imageUrl: $imageUrl,
        );
        $sessionRecommendation->setActive($saveSessionRecommendationInput->active);

        $this->sessionRecommendationRepository->save($sessionRecommendation);
    }
}
