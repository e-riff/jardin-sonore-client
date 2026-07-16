<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Application\Storage\SessionRecommendationImageStorageInterface;
use App\Domain\Model\Session\SessionRecommendation;
use App\Domain\Repository\SessionRecommendationRepositoryInterface;

final readonly class CreateSessionRecommendation
{
    public function __construct(
        private SessionRecommendationRepositoryInterface $sessionRecommendationRepository,
        private SessionRecommendationImageStorageInterface $sessionRecommendationImageStorage,
    ) {
    }

    public function __invoke(SaveSessionRecommendationInput $saveSessionRecommendationInput): SessionRecommendation
    {
        $imageUrl = null === $saveSessionRecommendationInput->imageFile
            ? $saveSessionRecommendationInput->imageUrl
            : $this->sessionRecommendationImageStorage->store($saveSessionRecommendationInput->imageFile);

        $sessionRecommendation = new SessionRecommendation(
            title: $saveSessionRecommendationInput->title,
            text: $saveSessionRecommendationInput->text,
            notes: $saveSessionRecommendationInput->notes,
            primaryUrl: $saveSessionRecommendationInput->primaryUrl,
            secondaryUrl: $saveSessionRecommendationInput->secondaryUrl,
            imageUrl: $imageUrl,
            active: $saveSessionRecommendationInput->active,
        );

        $this->sessionRecommendationRepository->save($sessionRecommendation);

        return $sessionRecommendation;
    }
}
