<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Application\Storage\SessionRecommendationImageStorageInterface;
use App\Domain\Repository\SessionRecommendationRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use Throwable;

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

        $imagePath = null === $saveSessionRecommendationInput->imageFile
            ? $saveSessionRecommendationInput->imagePath
            : $this->sessionRecommendationImageStorage->store($saveSessionRecommendationInput->imageFile);

        $sessionRecommendation->updateContent(
            title: $saveSessionRecommendationInput->title,
            text: $saveSessionRecommendationInput->text,
            notes: $saveSessionRecommendationInput->notes,
            primaryUrl: $saveSessionRecommendationInput->primaryUrl,
            secondaryUrl: $saveSessionRecommendationInput->secondaryUrl,
            imagePath: $imagePath,
        );
        $sessionRecommendation->setActive($saveSessionRecommendationInput->active);

        try {
            $this->sessionRecommendationRepository->save($sessionRecommendation);
        } catch (Throwable $exception) {
            if (null !== $saveSessionRecommendationInput->imageFile && null !== $imagePath) {
                $this->sessionRecommendationImageStorage->delete($imagePath);
            }
            throw $exception;
        }
    }
}
