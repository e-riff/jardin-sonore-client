<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\EmailContact;
use App\Domain\Model\AddressBook\EmailContactType;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;

final readonly class EmailContactMapper
{
    public function toDomain(EmailContactEntity $emailContactEntity): EmailContact
    {
        return new EmailContact(
            emailAddress: new EmailAddress($emailContactEntity->getEmailAddress()),
            label: null,
            type: EmailContactType::MAIN,
            optInNewsletter: $emailContactEntity->hasOptInNewsletter(),
            active: $emailContactEntity->isActive(),
            source: $emailContactEntity->getSource(),
            unsubscribedAt: $emailContactEntity->getUnsubscribedAt(),
            unsubscribeToken: $emailContactEntity->getUnsubscribeToken(),
            uuid: $emailContactEntity->getUuid(),
            id: $emailContactEntity->getId(),
            directoryMailingAllowed: $emailContactEntity->isDirectoryMailingAllowed(),
            newsletterRefused: $emailContactEntity->isNewsletterRefused(),
            newsletterRefusedAt: $emailContactEntity->getNewsletterRefusedAt(),
            outreachRefused: $emailContactEntity->isOutreachRefused(),
            outreachRefusedAt: $emailContactEntity->getOutreachRefusedAt(),
            freeNewsletterSubscription: $emailContactEntity->hasFreeNewsletterSubscription(),
            freeNewsletterSubscriptionConfirmedAt: $emailContactEntity->getFreeNewsletterSubscriptionConfirmedAt(),
            freeNewsletterSubscriptionOrigin: $emailContactEntity->getFreeNewsletterSubscriptionOrigin(),
        );
    }

    public function toEntity(EmailContact $emailContact, ?EmailContactEntity $emailContactEntity = null): EmailContactEntity
    {
        $emailContactEntity ??= new EmailContactEntity();

        $emailContactEntity
            ->setUuid($emailContact->getUuid())
            ->setEmailAddress($emailContact->getEmailAddress()->value())
            ->setOptInNewsletter($emailContact->hasNewsletterOptIn())
            ->setActive($emailContact->isActive())
            ->setSource($emailContact->getSource())
            ->setUnsubscribedAt($emailContact->getUnsubscribedAt())
            ->setUnsubscribeToken($emailContact->getUnsubscribeToken())
            ->setFreeNewsletterSubscription($emailContact->hasFreeNewsletterSubscription())
            ->setFreeNewsletterSubscriptionConfirmedAt($emailContact->getFreeNewsletterSubscriptionConfirmedAt())
            ->setFreeNewsletterSubscriptionOrigin($emailContact->getFreeNewsletterSubscriptionOrigin())
            ->setDirectoryMailingAllowed($emailContact->isDirectoryMailingAllowed())
            ->setNewsletterRefused($emailContact->isNewsletterRefused())
            ->setNewsletterRefusedAt($emailContact->getNewsletterRefusedAt())
            ->setOutreachRefused($emailContact->isOutreachRefused())
            ->setOutreachRefusedAt($emailContact->getOutreachRefusedAt());

        return $emailContactEntity;
    }
}
