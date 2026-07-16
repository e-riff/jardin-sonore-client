<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Session\SessionRecommendation;
use App\Infrastructure\Doctrine\Entity\SessionRecommendationEntity;

final readonly class SessionRecommendationMapper
{
    public function toDomain(SessionRecommendationEntity $sessionRecommendationEntity): SessionRecommendation
    {
        return new SessionRecommendation(
            title: $sessionRecommendationEntity->getTitle(),
            text: $sessionRecommendationEntity->getText(),
            notes: $sessionRecommendationEntity->getNotes(),
            primaryUrl: $sessionRecommendationEntity->getPrimaryUrl(),
            secondaryUrl: $sessionRecommendationEntity->getSecondaryUrl(),
            imageUrl: $sessionRecommendationEntity->getImageUrl(),
            active: $sessionRecommendationEntity->isActive(),
            createdAt: $sessionRecommendationEntity->getCreatedAt(),
            updatedAt: $sessionRecommendationEntity->getUpdatedAt(),
            uuid: $sessionRecommendationEntity->getUuid(),
        );
    }

    public function toEntity(SessionRecommendation $sessionRecommendation, ?SessionRecommendationEntity $sessionRecommendationEntity = null): SessionRecommendationEntity
    {
        $sessionRecommendationEntity ??= new SessionRecommendationEntity();
        $sessionRecommendationEntity
            ->setUuid($sessionRecommendation->getUuid())
            ->setTitle($sessionRecommendation->getTitle())
            ->setText($sessionRecommendation->getText())
            ->setNotes($sessionRecommendation->getNotes())
            ->setPrimaryUrl($sessionRecommendation->getPrimaryUrl())
            ->setSecondaryUrl($sessionRecommendation->getSecondaryUrl())
            ->setImageUrl($sessionRecommendation->getImageUrl())
            ->setActive($sessionRecommendation->isActive())
            ->setCreatedAt($sessionRecommendation->getCreatedAt())
            ->setUpdatedAt($sessionRecommendation->getUpdatedAt());

        return $sessionRecommendationEntity;
    }
}
