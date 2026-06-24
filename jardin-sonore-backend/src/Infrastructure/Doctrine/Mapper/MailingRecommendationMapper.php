<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Mailing\MailingRecommendation;
use App\Infrastructure\Doctrine\Entity\MailingRecommendationEntity;

final readonly class MailingRecommendationMapper
{
    public function toDomain(MailingRecommendationEntity $mailingRecommendationEntity): MailingRecommendation
    {
        return new MailingRecommendation(
            title: $mailingRecommendationEntity->getTitle(),
            text: $mailingRecommendationEntity->getText(),
            position: $mailingRecommendationEntity->getPosition(),
            url: $mailingRecommendationEntity->getUrl(),
            linkLabel: $mailingRecommendationEntity->getLinkLabel(),
            imagePath: $mailingRecommendationEntity->getImagePath(),
            active: $mailingRecommendationEntity->isActive(),
            uuid: $mailingRecommendationEntity->getUuid(),
        );
    }

    public function toEntity(MailingRecommendation $mailingRecommendation, ?MailingRecommendationEntity $mailingRecommendationEntity = null): MailingRecommendationEntity
    {
        $mailingRecommendationEntity ??= new MailingRecommendationEntity();

        $mailingRecommendationEntity
            ->setUuid($mailingRecommendation->getUuid())
            ->setTitle($mailingRecommendation->getTitle())
            ->setText($mailingRecommendation->getText())
            ->setPosition($mailingRecommendation->getPosition())
            ->setUrl($mailingRecommendation->getUrl())
            ->setLinkLabel($mailingRecommendation->getLinkLabel())
            ->setImagePath($mailingRecommendation->getImagePath())
            ->setActive($mailingRecommendation->isActive());

        return $mailingRecommendationEntity;
    }
}
