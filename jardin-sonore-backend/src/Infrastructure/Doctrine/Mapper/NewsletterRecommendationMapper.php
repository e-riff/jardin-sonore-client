<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Mailing\NewsletterRecommendation;
use App\Infrastructure\Doctrine\Entity\NewsletterRecommendationEntity;

final readonly class NewsletterRecommendationMapper
{
    public function toDomain(NewsletterRecommendationEntity $newsletterRecommendationEntity): NewsletterRecommendation
    {
        return new NewsletterRecommendation(
            title: $newsletterRecommendationEntity->getTitle(),
            tag: $newsletterRecommendationEntity->getTag(),
            text: $newsletterRecommendationEntity->getText(),
            url: $newsletterRecommendationEntity->getUrl(),
            linkLabel: $newsletterRecommendationEntity->getLinkLabel(),
            imagePath: $newsletterRecommendationEntity->getImagePath(),
            active: $newsletterRecommendationEntity->isActive(),
            createdAt: $newsletterRecommendationEntity->getCreatedAt(),
            updatedAt: $newsletterRecommendationEntity->getUpdatedAt(),
            uuid: $newsletterRecommendationEntity->getUuid(),
        );
    }

    public function toEntity(
        NewsletterRecommendation $newsletterRecommendation,
        ?NewsletterRecommendationEntity $newsletterRecommendationEntity = null,
    ): NewsletterRecommendationEntity {
        $newsletterRecommendationEntity ??= new NewsletterRecommendationEntity();

        $newsletterRecommendationEntity
            ->setUuid($newsletterRecommendation->getUuid())
            ->setTitle($newsletterRecommendation->getTitle())
            ->setTag($newsletterRecommendation->getTag())
            ->setText($newsletterRecommendation->getText())
            ->setUrl($newsletterRecommendation->getUrl())
            ->setLinkLabel($newsletterRecommendation->getLinkLabel())
            ->setImagePath($newsletterRecommendation->getImagePath())
            ->setActive($newsletterRecommendation->isActive())
            ->setCreatedAt($newsletterRecommendation->getCreatedAt())
            ->setUpdatedAt($newsletterRecommendation->getUpdatedAt());

        return $newsletterRecommendationEntity;
    }
}
