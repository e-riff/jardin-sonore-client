<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EmailContactLinkEntityTest extends TestCase
{
    public function testAddressCorrectionPreservesOldConsentAndCreatesUnconsentedContact(): void
    {
        $oldEmailContactEntity = (new EmailContactEntity())->setEmailAddress('old@example.test');
        $oldEmailContactEntity->confirmFreeNewsletterSubscription(new DateTimeImmutable('2026-10-01 12:00:00'), 'backoffice');
        $emailContactLinkEntity = (new EmailContactLinkEntity())->setEmailContact($oldEmailContactEntity);
        $emailContactLinkEntity->setEmailAddress('new@example.test');
        self::assertSame('old@example.test', $oldEmailContactEntity->getEmailAddress());
        self::assertTrue($oldEmailContactEntity->hasOptInNewsletter());
        self::assertSame('new@example.test', $emailContactLinkEntity->getEmailAddress());
        self::assertFalse($emailContactLinkEntity->getEmailContact()?->hasOptInNewsletter());
        self::assertFalse($emailContactLinkEntity->getEmailContact()?->hasFreeNewsletterSubscription());
        self::assertNull($emailContactLinkEntity->getEmailContact()?->getFreeNewsletterSubscriptionConfirmedAt());
        self::assertTrue($oldEmailContactEntity->getEmailContactLinks()->isEmpty());
    }

    public function testReplacingTheSharedEmailContactDetachesTheLinkFromThePreviousContact(): void
    {
        $previousEmailContact = (new EmailContactEntity())->setEmailAddress('previous@example.test');
        $existingEmailContact = (new EmailContactEntity())->setEmailAddress('existing@example.test');
        $emailContactLink = (new EmailContactLinkEntity())->setEmailContact($previousEmailContact);

        $emailContactLink->setEmailContact($existingEmailContact);

        self::assertTrue($previousEmailContact->getEmailContactLinks()->isEmpty());
        self::assertTrue($existingEmailContact->getEmailContactLinks()->contains($emailContactLink));
        self::assertSame($existingEmailContact, $emailContactLink->getEmailContact());
    }
}
