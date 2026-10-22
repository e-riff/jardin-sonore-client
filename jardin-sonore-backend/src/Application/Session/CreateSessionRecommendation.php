<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Application\Storage\SessionRecommendationImageStorageInterface;
use App\Domain\Model\Session\SessionRecommendation;
use App\Domain\Repository\SessionRecommendationRepositoryInterface;
use Throwable;

final readonly class CreateSessionRecommendation
{
    public function __construct(
        private SessionRecommendationRepositoryInterface $sessionRecommendationRepository,
        private SessionRecommendationImageStorageInterface $sessionRecommendationImageStorage,
    ) {
    }

    public function __invoke(SaveSessionRecommendationInput $saveSessionRecommendationInput): SessionRecommendation
    {
        $imagePath = null === $saveSessionRecommendationInput->imageFile
            ? $saveSessionRecommendationInput->imagePath
            : $this->sessionRecommendationImageStorage->store($saveSessionRecommendationInput->imageFile);

        $sessionRecommendation = new SessionRecommendation(
            title: $saveSessionRecommendationInput->title,
            text: $saveSessionRecommendationInput->text,
            notes: $saveSessionRecommendationInput->notes,
            primaryUrl: $saveSessionRecommendationInput->primaryUrl,
            secondaryUrl: $saveSessionRecommendationInput->secondaryUrl,
            imagePath: $imagePath,
            active: $saveSessionRecommendationInput->active,
        );

        try {
            $this->sessionRecommendationRepository->save($sessionRecommendation);
        } catch (Throwable $exception) {
            if (null !== $saveSessionRecommendationInput->imageFile && null !== $imagePath) {
                $this->sessionRecommendationImageStorage->delete($imagePath);
            }
            throw $exception;
        }

        return $sessionRecommendation;
    }
}
