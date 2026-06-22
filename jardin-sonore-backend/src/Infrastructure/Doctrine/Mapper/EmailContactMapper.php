<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\EmailContact;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;

final readonly class EmailContactMapper
{
    public function toDomain(EmailContactEntity $emailContactEntity): EmailContact
    {
        return new EmailContact(
            emailAddress: new EmailAddress($emailContactEntity->getEmailAddress()),
            label: $emailContactEntity->getLabel(),
            type: $emailContactEntity->getType(),
            optInNewsletter: $emailContactEntity->hasOptInNewsletter(),
            active: $emailContactEntity->isActive(),
            source: $emailContactEntity->getSource(),
            unsubscribedAt: $emailContactEntity->getUnsubscribedAt(),
            unsubscribeToken: $emailContactEntity->getUnsubscribeToken(),
            uuid: $emailContactEntity->getUuid(),
            id: $emailContactEntity->getId(),
        );
    }

    public function toEntity(EmailContact $emailContact, ?EmailContactEntity $emailContactEntity = null): EmailContactEntity
    {
        $emailContactEntity ??= new EmailContactEntity();

        $emailContactEntity
            ->setUuid($emailContact->getUuid())
            ->setEmailAddress($emailContact->getEmailAddress()->value())
            ->setLabel($emailContact->getLabel())
            ->setType($emailContact->getType())
            ->setOptInNewsletter($emailContact->hasNewsletterOptIn())
            ->setActive($emailContact->isActive())
            ->setSource($emailContact->getSource())
            ->setUnsubscribedAt($emailContact->getUnsubscribedAt())
            ->setUnsubscribeToken($emailContact->getUnsubscribeToken());

        return $emailContactEntity;
    }
}
