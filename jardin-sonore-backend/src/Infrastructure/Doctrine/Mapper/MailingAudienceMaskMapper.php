<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Infrastructure\Doctrine\Entity\MailingAudienceMaskEntity;

final readonly class MailingAudienceMaskMapper
{
    public function __construct(private NewsletterAudienceFilterArrayMapper $newsletterAudienceFilterArrayMapper)
    {
    }

    public function toDomain(MailingAudienceMaskEntity $mailingAudienceMaskEntity): MailingAudienceMask
    {
        return new MailingAudienceMask(
            name: $mailingAudienceMaskEntity->getName(),
            audienceFilter: $this->newsletterAudienceFilterArrayMapper->toDomain($mailingAudienceMaskEntity->getAudienceFilter()),
            materializedMunicipalityInseeCodes: $mailingAudienceMaskEntity->getMaterializedMunicipalityInseeCodes(),
            createdAt: $mailingAudienceMaskEntity->getCreatedAt(),
            updatedAt: $mailingAudienceMaskEntity->getUpdatedAt(),
            uuid: $mailingAudienceMaskEntity->getUuid(),
        );
    }

    public function toEntity(
        MailingAudienceMask $mailingAudienceMask,
        ?MailingAudienceMaskEntity $mailingAudienceMaskEntity = null,
    ): MailingAudienceMaskEntity {
        $mailingAudienceMaskEntity ??= new MailingAudienceMaskEntity();

        return $mailingAudienceMaskEntity
            ->setUuid($mailingAudienceMask->getUuid())
            ->setName($mailingAudienceMask->getName())
            ->setAudienceFilter($this->newsletterAudienceFilterArrayMapper->toArray($mailingAudienceMask->getAudienceFilter()))
            ->setMaterializedMunicipalityInseeCodes($mailingAudienceMask->getMaterializedMunicipalityInseeCodes())
            ->setCreatedAt($mailingAudienceMask->getCreatedAt())
            ->setUpdatedAt($mailingAudienceMask->getUpdatedAt());
    }
}
