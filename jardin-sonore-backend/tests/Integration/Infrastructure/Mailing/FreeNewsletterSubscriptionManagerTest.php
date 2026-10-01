<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Application\Mailing\NewsletterConfirmationMailSenderInterface;
use App\Application\Mailing\NewsletterConfirmationState;
use App\Application\Mailing\UnsubscribeNewsletterRecipient;
use App\Infrastructure\Admin\EmailContactCrudController;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\NewsletterSubscriptionRequestDoctrineRepository;
use App\Infrastructure\Mailing\FreeNewsletterSubscriptionManager;
use App\Infrastructure\Portal\PortalNewsletterSubscriptionManager;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class FreeNewsletterSubscriptionManagerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private FreeNewsletterSubscriptionManager $manager;
    private MockClock $clock;
    private string $emailAddress;
    private CapturingNewsletterConfirmationSender $sender;

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
        $this->clock = new MockClock('2026-10-01 12:00:00 UTC');
        $this->sender = new CapturingNewsletterConfirmationSender();
        $this->emailAddress = 'free-' . bin2hex(random_bytes(8)) . '@example.test';
        $this->manager = new FreeNewsletterSubscriptionManager(
            $this->entityManager,
            self::getContainer()->get(EmailContactDoctrineRepository::class),
            self::getContainer()->get(NewsletterSubscriptionRequestDoctrineRepository::class),
            $this->clock,
            $this->sender,
        );
    }

    protected function tearDown(): void
    {
        while ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testRequestAndGetDoNotActivateButPostConfirms(): void
    {
        $this->manager->requestSubscription('  ' . strtoupper($this->emailAddress) . '  ');
        self::assertFalse($this->contact()->hasOptInNewsletter());
        self::assertFalse($this->contact()->hasFreeNewsletterSubscription());
        $rawToken = $this->sender->tokens[0];
        self::assertSame(NewsletterConfirmationState::READY, $this->manager->confirmationState($rawToken));
        self::assertFalse($this->contact()->hasOptInNewsletter());
        $row = $this->entityManager->getConnection()->fetchAssociative('SELECT token_hash, expires_at FROM newsletter_subscription_request WHERE email_contact_id = ?', [$this->contact()->getId()]);
        self::assertIsArray($row);
        self::assertSame(hash('sha256', $rawToken), $row['token_hash']);
        self::assertSame('2026-10-03 12:00:00', $row['expires_at']);
        self::assertSame(NewsletterConfirmationState::CONFIRMED, $this->manager->confirm($rawToken));
        self::assertTrue($this->contact()->hasOptInNewsletter());
        self::assertSame('footer', $this->contact()->getFreeNewsletterSubscriptionOrigin());
    }

    public function testConsumedTokenCannotReactivateUnsubscribedContact(): void
    {
        $this->manager->requestSubscription($this->emailAddress);
        $rawToken = $this->sender->tokens[0];
        $this->manager->confirm($rawToken);
        $confirmedAt = $this->contact()->getFreeNewsletterSubscriptionConfirmedAt();
        $this->manager->unsubscribeFromBackoffice($this->contact()->getUuid()->toRfc4122());
        self::assertSame(NewsletterConfirmationState::CONSUMED, $this->manager->confirm($rawToken));
        self::assertFalse($this->contact()->hasOptInNewsletter());
        self::assertEquals($confirmedAt, $this->contact()->getFreeNewsletterSubscriptionConfirmedAt());
    }

    public function testResendCooldownAndReplacementInvalidateOldToken(): void
    {
        $this->manager->requestSubscription($this->emailAddress);
        $oldToken = $this->sender->tokens[0];
        $this->clock->sleep(59);
        $this->manager->requestSubscription($this->emailAddress);
        self::assertCount(1, $this->sender->tokens);
        $this->clock->sleep(1);
        $this->manager->requestSubscription($this->emailAddress);
        self::assertCount(2, $this->sender->tokens);
        self::assertSame(NewsletterConfirmationState::UNAVAILABLE, $this->manager->confirm($oldToken));
        self::assertSame(NewsletterConfirmationState::READY, $this->manager->confirmationState($this->sender->tokens[1]));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM email_contact WHERE email_address = ?', [$this->emailAddress]));
    }

    public function testExpiryAtFortyEightHoursPreventsActivation(): void
    {
        $this->manager->requestSubscription($this->emailAddress);
        $this->clock->sleep(48 * 3600);
        self::assertSame(NewsletterConfirmationState::UNAVAILABLE, $this->manager->confirm($this->sender->tokens[0]));
        self::assertFalse($this->contact()->hasOptInNewsletter());
    }

    public function testManualAttestationActivatesAndRepeatedAdditionPreservesOrigin(): void
    {
        $emailContactEntity = $this->manager->subscribeFromBackoffice($this->emailAddress, true);
        $confirmedAt = $emailContactEntity->getFreeNewsletterSubscriptionConfirmedAt();
        self::assertTrue($emailContactEntity->hasOptInNewsletter());
        self::assertSame('backoffice', $emailContactEntity->getFreeNewsletterSubscriptionOrigin());
        $this->clock->sleep(3600);
        $this->manager->subscribeFromBackoffice($this->emailAddress, true);
        self::assertEquals($confirmedAt, $this->contact()->getFreeNewsletterSubscriptionConfirmedAt());
        $this->manager->requestSubscription($this->emailAddress);
        self::assertCount(0, $this->sender->tokens);
    }

    public function testManualActivationRequiresAttestation(): void
    {
        $this->expectException(DomainException::class);
        $this->manager->subscribeFromBackoffice($this->emailAddress, false);
    }

    public function testBlockedContactCannotBeActivatedByConfirmation(): void
    {
        $this->manager->requestSubscription($this->emailAddress);
        $this->contact()->setActive(false);
        $this->entityManager->flush();
        self::assertSame(NewsletterConfirmationState::UNAVAILABLE, $this->manager->confirm($this->sender->tokens[0]));
        self::assertFalse($this->contact()->hasOptInNewsletter());
    }

    public function testSendFailureDoesNotGrantConsentAndCanBeRetriedAfterCooldown(): void
    {
        $this->sender->fail = true;
        try {
            $this->manager->requestSubscription($this->emailAddress);
            self::fail('SMTP failure must propagate.');
        } catch (RuntimeException) {
            self::assertFalse($this->contact()->hasOptInNewsletter());
        }
        $this->sender->fail = false;
        $this->clock->sleep(60);
        $this->manager->requestSubscription($this->emailAddress);
        self::assertSame(NewsletterConfirmationState::READY, $this->manager->confirmationState($this->sender->tokens[1]));
    }

    public function testPendingContactCannotBeRenamedAndTokenKeepsOriginalAddress(): void
    {
        $this->manager->requestSubscription($this->emailAddress);
        $emailContactEntity = $this->contact();
        $emailContactEntity->setEmailAddress('corrected@example.test');
        try {
            self::getContainer()->get(EmailContactCrudController::class)->updateEntity($this->entityManager, $emailContactEntity);
            self::fail('Pending addresses must not be rewritten.');
        } catch (BadRequestHttpException) {
            $this->entityManager->refresh($emailContactEntity);
        }
        self::assertSame($this->emailAddress, $emailContactEntity->getEmailAddress());
        self::assertSame(NewsletterConfirmationState::CONFIRMED, $this->manager->confirm($this->sender->tokens[0]));
    }

    public function testPortalWithdrawalInvalidatesPendingLink(): void
    {
        $this->manager->requestSubscription($this->emailAddress);
        $userEntity = (new UserEntity())->setEmail($this->emailAddress);
        self::getContainer()->get(PortalNewsletterSubscriptionManager::class)->setEnabled($userEntity, false);
        $this->entityManager->flush();
        self::assertSame(NewsletterConfirmationState::CONSUMED, $this->manager->confirm($this->sender->tokens[0]));
        self::assertFalse($this->contact()->hasOptInNewsletter());
    }

    public function testPublicWithdrawalInvalidatesOldLinkButAllowsNewRequest(): void
    {
        $this->manager->requestSubscription($this->emailAddress);
        $oldToken = $this->sender->tokens[0];
        self::assertTrue((self::getContainer()->get(UnsubscribeNewsletterRecipient::class))($this->contact()->getUnsubscribeToken()));
        self::assertSame(NewsletterConfirmationState::CONSUMED, $this->manager->confirm($oldToken));
        self::assertFalse($this->contact()->hasOptInNewsletter());
        $this->clock->sleep(60);
        $this->manager->requestSubscription($this->emailAddress);
        self::assertSame(NewsletterConfirmationState::CONFIRMED, $this->manager->confirm($this->sender->tokens[1]));
    }

    private function contact(): EmailContactEntity
    {
        $emailContactEntity = self::getContainer()->get(EmailContactDoctrineRepository::class)->findEntityByEmailAddress($this->emailAddress);
        self::assertInstanceOf(EmailContactEntity::class, $emailContactEntity);
        $this->entityManager->refresh($emailContactEntity);

        return $emailContactEntity;
    }
}

final class CapturingNewsletterConfirmationSender implements NewsletterConfirmationMailSenderInterface
{
    /** @var list<string> */
    public array $tokens = [];
    public bool $fail = false;

    public function sendConfirmation(string $emailAddress, string $rawToken): void
    {
        $this->tokens[] = $rawToken;
        if ($this->fail) {
            throw new RuntimeException('Test SMTP failure.');
        }
    }
}
