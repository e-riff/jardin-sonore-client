<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Portal;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Portal\PortalNewsletterSubscriptionManager;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PortalNewsletterSubscriptionManagerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserEntity $userEntity;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertStringEndsWith('_test', (string) $this->entityManager->getConnection()->getDatabase());
        $this->entityManager->getConnection()->beginTransaction();
        $this->userEntity = (new UserEntity())->setEmail('subscription-' . bin2hex(random_bytes(8)) . '@example.test');
    }

    protected function tearDown(): void
    {
        while ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testReadingOrDisablingAnUnknownAddressDoesNotCreateAContact(): void
    {
        $manager = $this->manager();
        self::assertFalse($manager->isEnabled($this->userEntity));
        $manager->setEnabled($this->userEntity, false);
        $this->entityManager->flush();

        self::assertSame(0, $this->contactCount());
    }

    public function testExplicitSubscriptionCreatesOneContactWithoutFreeMembership(): void
    {
        $manager = $this->manager();
        $manager->setEnabled($this->userEntity, true);
        $this->entityManager->flush();
        $manager->setEnabled($this->userEntity, true);
        $this->entityManager->flush();

        $emailContactEntity = $this->contact();
        self::assertSame(1, $this->contactCount());
        self::assertTrue($manager->isEnabled($this->userEntity));
        self::assertFalse($emailContactEntity->hasFreeNewsletterSubscription());
        self::assertNull($emailContactEntity->getFreeNewsletterSubscriptionConfirmedAt());
        self::assertFalse($this->userEntity->isNewSessionNotificationsEnabled());
    }

    public function testWithdrawalAndVoluntaryResubscriptionPreserveAddressHistory(): void
    {
        $emailContactEntity = $this->createContact();
        $confirmedAt = new DateTimeImmutable('2026-09-30 12:00:00');
        $emailContactEntity->confirmFreeNewsletterSubscription($confirmedAt, 'public_footer');
        $this->entityManager->flush();
        $unsubscribeToken = $emailContactEntity->getUnsubscribeToken();
        $manager = $this->manager();

        $manager->setEnabled($this->userEntity, false);
        $this->entityManager->flush();
        self::assertFalse($manager->isEnabled($this->userEntity));
        self::assertNotNull($emailContactEntity->getUnsubscribedAt());
        $manager->setEnabled($this->userEntity, true);
        $this->entityManager->flush();

        self::assertTrue($manager->isEnabled($this->userEntity));
        self::assertNull($emailContactEntity->getUnsubscribedAt());
        self::assertSame($unsubscribeToken, $emailContactEntity->getUnsubscribeToken());
        self::assertEquals($confirmedAt, $emailContactEntity->getFreeNewsletterSubscriptionConfirmedAt());
        self::assertSame('public_footer', $emailContactEntity->getFreeNewsletterSubscriptionOrigin());
        self::assertSame(1, $this->contactCount());
    }

    public function testBlockedAddressCannotBeReactivated(): void
    {
        $emailContactEntity = $this->createContact();
        $emailContactEntity->setActive(false)->setOptInNewsletter(false);
        $this->entityManager->flush();
        $manager = $this->manager();
        self::assertFalse($manager->isEnabled($this->userEntity));

        try {
            $manager->setEnabled($this->userEntity, true);
            self::fail('A blocked address must not be reactivated by a profile update.');
        } catch (DomainException) {
            self::assertFalse($emailContactEntity->isActive());
            self::assertFalse($emailContactEntity->hasOptInNewsletter());
        }
    }

    public function testExternalUnsubscriptionIsReflectedInProfileState(): void
    {
        $emailContactEntity = $this->createContact();
        self::assertTrue($this->manager()->isEnabled($this->userEntity));
        $emailContactEntity->setOptInNewsletter(false)->setUnsubscribedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        self::assertFalse($this->manager()->isEnabled($this->userEntity));
    }

    public function testChangedAccountAddressDoesNotTransferOldConsent(): void
    {
        $emailContactEntity = $this->createContact();
        $this->userEntity->setEmail('changed-' . bin2hex(random_bytes(8)) . '@example.test');

        self::assertFalse($this->manager()->isEnabled($this->userEntity));
        self::assertSame(0, $this->contactCount());
        self::assertTrue($emailContactEntity->hasOptInNewsletter());
    }

    private function manager(): PortalNewsletterSubscriptionManager
    {
        return self::getContainer()->get(PortalNewsletterSubscriptionManager::class);
    }

    private function createContact(): EmailContactEntity
    {
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress($this->userEntity->getEmail());
        $this->entityManager->persist($emailContactEntity);
        $this->entityManager->flush();

        return $emailContactEntity;
    }

    private function contact(): EmailContactEntity
    {
        $emailContactEntity = $this->entityManager->getRepository(EmailContactEntity::class)->findOneBy(['emailAddress' => $this->userEntity->getEmail()]);
        self::assertInstanceOf(EmailContactEntity::class, $emailContactEntity);

        return $emailContactEntity;
    }

    private function contactCount(): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM email_contact WHERE email_address = ?', [$this->userEntity->getEmail()]);
    }
}
