<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\EmailContact;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Mapper\EmailContactMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EmailContactMapperTest extends TestCase
{
    public function testHistoricalProjectionRemainsConservativeWithUndatedRefusal(): void
    {
        $emailContactEntity = (new EmailContactEntity())->setDirectoryMailingAllowed(true)->setNewsletterRefused(true);
        $emailContactEntity->refreshHistoricalMailingPreferences();
        self::assertFalse($emailContactEntity->hasOptInNewsletter());
        self::assertNull($emailContactEntity->getUnsubscribedAt());
        $date = new DateTimeImmutable('2026-11-27');
        $emailContactEntity->setOutreachRefused(true)->setOutreachRefusedAt($date)->refreshHistoricalMailingPreferences();
        self::assertSame($date, $emailContactEntity->getUnsubscribedAt());
        $emailContactEntity->setNewsletterRefused(false)->refreshHistoricalMailingPreferences();
        self::assertFalse($emailContactEntity->hasOptInNewsletter());
        $emailContactEntity->setOutreachRefused(false)->refreshHistoricalMailingPreferences();
        self::assertTrue($emailContactEntity->hasOptInNewsletter());
        self::assertNull($emailContactEntity->getUnsubscribedAt());
    }

    public function testIndependentPreferencesSurviveMappingWithoutInventingDates(): void
    {
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress('shared@example.test')
            ->setDirectoryMailingAllowed(false)->setNewsletterRefused(true)
            ->setOutreachRefused(true)->setOutreachRefusedAt(new DateTimeImmutable('2026-11-27 09:00:00'));
        $emailContactMapper = new EmailContactMapper();
        $emailContact = $emailContactMapper->toDomain($emailContactEntity);
        $restoredEmailContactEntity = $emailContactMapper->toEntity($emailContact);

        self::assertFalse($restoredEmailContactEntity->isDirectoryMailingAllowed());
        self::assertTrue($restoredEmailContactEntity->isNewsletterRefused());
        self::assertNull($restoredEmailContactEntity->getNewsletterRefusedAt());
        self::assertTrue($restoredEmailContactEntity->isOutreachRefused());
        self::assertSame($emailContactEntity->getOutreachRefusedAt(), $restoredEmailContactEntity->getOutreachRefusedAt());
    }

    public function testHistoricalConsentDoesNotBecomeAFreeSubscription(): void
    {
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress('historical@example.test');
        $emailContactMapper = new EmailContactMapper();

        $emailContact = $emailContactMapper->toDomain($emailContactEntity);
        $restoredEmailContactEntity = $emailContactMapper->toEntity($emailContact);

        self::assertTrue($restoredEmailContactEntity->hasOptInNewsletter());
        self::assertFalse($emailContact->hasFreeNewsletterSubscription());
        self::assertFalse($restoredEmailContactEntity->hasFreeNewsletterSubscription());
        self::assertNull($restoredEmailContactEntity->getFreeNewsletterSubscriptionConfirmedAt());
        self::assertNull($restoredEmailContactEntity->getFreeNewsletterSubscriptionOrigin());
    }

    public function testConfirmedFreeSubscriptionSurvivesAMapperRoundTrip(): void
    {
        $confirmedAt = new DateTimeImmutable('2026-09-30 12:00:00');
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress('free@example.test');
        $emailContactEntity->confirmFreeNewsletterSubscription($confirmedAt, 'public_footer');
        $unsubscribeToken = $emailContactEntity->getUnsubscribeToken();
        $emailContactMapper = new EmailContactMapper();

        $emailContact = $emailContactMapper->toDomain($emailContactEntity);
        $restoredEmailContactEntity = $emailContactMapper->toEntity($emailContact);

        self::assertTrue($emailContact->hasFreeNewsletterSubscription());
        self::assertSame($confirmedAt, $emailContact->getFreeNewsletterSubscriptionConfirmedAt());
        self::assertSame('public_footer', $emailContact->getFreeNewsletterSubscriptionOrigin());
        self::assertTrue($restoredEmailContactEntity->hasFreeNewsletterSubscription());
        self::assertSame($confirmedAt, $restoredEmailContactEntity->getFreeNewsletterSubscriptionConfirmedAt());
        self::assertSame('public_footer', $restoredEmailContactEntity->getFreeNewsletterSubscriptionOrigin());
        self::assertSame($unsubscribeToken, $restoredEmailContactEntity->getUnsubscribeToken());
    }

    public function testUnsubscribePreservesFreeSubscriptionHistory(): void
    {
        $confirmedAt = new DateTimeImmutable('2026-09-30 12:00:00');
        $unsubscribedAt = new DateTimeImmutable('2026-09-30 13:00:00');
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress('free@example.test');
        $emailContactEntity->confirmFreeNewsletterSubscription($confirmedAt, 'public_footer');
        $emailContactMapper = new EmailContactMapper();
        $emailContact = $emailContactMapper->toDomain($emailContactEntity);

        $emailContact->unsubscribe($unsubscribedAt);
        $emailContactMapper->toEntity($emailContact, $emailContactEntity);

        self::assertFalse($emailContactEntity->hasOptInNewsletter());
        self::assertSame($unsubscribedAt, $emailContactEntity->getUnsubscribedAt());
        self::assertTrue($emailContactEntity->hasFreeNewsletterSubscription());
        self::assertSame($confirmedAt, $emailContactEntity->getFreeNewsletterSubscriptionConfirmedAt());
        self::assertSame('public_footer', $emailContactEntity->getFreeNewsletterSubscriptionOrigin());
    }

    public function testFreeSubscriptionConfirmationDoesNotReactivateConsentOrAddress(): void
    {
        $unsubscribedAt = new DateTimeImmutable('2026-09-29 12:00:00');
        $emailContactEntity = (new EmailContactEntity())
            ->setEmailAddress('unsubscribed@example.test')
            ->setActive(false)
            ->setOptInNewsletter(false)
            ->setUnsubscribedAt($unsubscribedAt);

        $emailContactEntity->confirmFreeNewsletterSubscription(new DateTimeImmutable('2026-09-30 12:00:00'), 'public_footer');

        self::assertTrue($emailContactEntity->hasFreeNewsletterSubscription());
        self::assertFalse($emailContactEntity->hasOptInNewsletter());
        self::assertFalse($emailContactEntity->isActive());
        self::assertSame($unsubscribedAt, $emailContactEntity->getUnsubscribedAt());
    }

    public function testExistingDomainConstructorKeepsHistoricalDefaults(): void
    {
        $emailContact = new EmailContact(new EmailAddress('historical@example.test'));

        self::assertTrue($emailContact->hasNewsletterOptIn());
        self::assertFalse($emailContact->hasFreeNewsletterSubscription());
        self::assertNull($emailContact->getFreeNewsletterSubscriptionConfirmedAt());
        self::assertNull($emailContact->getFreeNewsletterSubscriptionOrigin());
    }
}
