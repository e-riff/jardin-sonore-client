<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Session;

use App\Application\Command\DispatchSessionNotificationsCommand;
use App\Application\Session\Message\SendSessionNotificationMessage;
use App\Application\Session\MessageHandler\SendSessionNotificationHandler;
use App\Application\Session\SessionNotificationMailSenderInterface;
use App\Application\Session\SessionNotificationMailView;
use App\Application\Session\SetSessionPublication;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\SessionNotificationDeliveryEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use App\Infrastructure\Session\SessionNotificationDeliveryStore;
use App\Infrastructure\Session\SessionNotificationRecipientReader;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class SessionNotificationDeliveryTest extends KernelTestCase
{
    private Connection $connection;
    private SessionNotificationDeliveryStore $store;
    private UserEntity $userEntity;
    private SessionSummaryEntity $sessionSummaryEntity;
    private int $deliveryId;
    private SessionNotificationMailSenderInterface $sender;
    /** @var list<SessionNotificationMailView> */
    private array $sent = [];
    private bool $failSending = false;
    private bool $fixturesCommitted = false;
    /** @var list<OrganizationEntity> */
    private array $fixtureOrganizations = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $entityManager->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
        $this->store = self::getContainer()->get(SessionNotificationDeliveryStore::class);
        $organization = (new OrganizationEntity())->setName('Structure accessible');
        $otherOrganization = (new OrganizationEntity())->setName('Autre accès du compte');
        $forbiddenOrganization = (new OrganizationEntity())->setName('Structure sans accès');
        foreach ([$organization, $otherOrganization, $forbiddenOrganization] as $organizationEntity) {
            $entityManager->persist($organizationEntity);
            $this->fixtureOrganizations[] = $organizationEntity;
        }
        $this->userEntity = (new UserEntity())->setEmail('delivery-' . bin2hex(random_bytes(8)) . '@portal.test')->setPassword('unused')->setStatus(UserStatus::ACTIVE)->setActive(true)->setNewSessionNotificationsEnabled(true);
        foreach ([$organization, $otherOrganization] as $organizationEntity) {
            $this->userEntity->addOrganizationAccess((new UserOrganizationAccessEntity())->setOrganization($organizationEntity)->setActive(true));
        }
        $this->sessionSummaryEntity = (new SessionSummaryEntity())->setTitle('Recette notification')->replaceOrganizations([$organization, $forbiddenOrganization]);
        $entityManager->persist($this->userEntity);
        $entityManager->persist($this->sessionSummaryEntity);
        $entityManager->flush();
        self::getContainer()->get(SetSessionPublication::class)($this->sessionSummaryEntity->getUuid(), true);
        $this->deliveryId = (int) $this->connection->fetchOne('SELECT id FROM session_notification_delivery WHERE session_summary_id = ? AND user_id = ?', [$this->sessionSummaryEntity->getId(), $this->userEntity->getId()]);
        self::assertGreaterThan(0, $this->deliveryId);
        $this->sender = new class(function (SessionNotificationMailView $view): void {
            if ($this->failSending) {
                throw new RuntimeException('SMTP indisponible');
            }
            $this->sent[] = $view;
        }) implements SessionNotificationMailSenderInterface {
            /** @param callable(SessionNotificationMailView): void $send */
            public function __construct(private mixed $send)
            {
            }

            public function send(SessionNotificationMailView $sessionNotificationMailView): void
            {
                ($this->send)($sessionNotificationMailView);
            }
        };
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        if ($this->fixturesCommitted) {
            $this->connection->delete('session_summary', ['id' => $this->sessionSummaryEntity->getId()]);
            $this->connection->delete('portal_user', ['id' => $this->userEntity->getId()]);
            foreach ($this->fixtureOrganizations as $organizationEntity) {
                $this->connection->delete('directory_entry', ['id' => $organizationEntity->getId()]);
            }
        }
        parent::tearDown();
    }

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-sonore-session-notification-test-cache';
            }
        };
    }

    public function testPublicationQueueingSendingAndRepublishingSendOnlyOnce(): void
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $transport->reset();
        $commandTester = new CommandTester(new DispatchSessionNotificationsCommand($this->store, self::getContainer()->get(MessageBusInterface::class), $this->sender));
        self::assertSame(0, $commandTester->execute([]));
        $messages = array_filter(array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $transport->getSent()), fn (object $message): bool => $message instanceof SendSessionNotificationMessage && $message->deliveryId === $this->deliveryId);
        self::assertCount(1, $messages);
        $this->handle();
        $this->handle();
        $publish = self::getContainer()->get(SetSessionPublication::class);
        $publish($this->sessionSummaryEntity->getUuid(), false);
        $publish($this->sessionSummaryEntity->getUuid(), true);
        self::assertSame(0, $this->store->dispatchPending(self::getContainer()->get(MessageBusInterface::class)));
        self::assertCount(1, $this->sent);
        self::assertSame('sent', $this->state()['status']);
        self::assertNotNull($this->state()['sent_at']);
    }

    public function testInterruptedQueueingIsRecoveredAfterConfiguredDelay(): void
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);
        self::assertSame(1, $this->store->dispatchPending($bus, 30));
        self::assertSame(0, $this->store->dispatchPending($bus, 30));
        $this->connection->update('session_notification_delivery', ['queued_at' => '2000-01-01 00:00:00'], ['id' => $this->deliveryId]);
        self::assertSame(1, $this->store->dispatchPending($bus, 30));
        $this->handle();
        $this->handle();
        self::assertCount(1, $this->sent);
    }

    public function testQueueFailureLeavesPendingDeliveryAvailable(): void
    {
        $bus = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new RuntimeException('Queue unavailable');
            }
        };
        try {
            $this->store->dispatchPending($bus);
            self::fail('Expected queue failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Queue unavailable', $exception->getMessage());
        }
        self::assertSame('pending', $this->state()['status']);
        self::assertContains($this->deliveryId, $this->store->queueableIds());
    }

    public function testCurrentEmailAndOnlyAuthorizedOrganizationsAreUsed(): void
    {
        $this->connection->update('portal_user', ['email' => 'changed-' . bin2hex(random_bytes(8)) . '@portal.test'], ['id' => $this->userEntity->getId()]);
        $this->handle();
        self::assertCount(1, $this->sent);
        self::assertNotSame($this->userEntity->getEmail(), $this->sent[0]->email);
        self::assertSame(['Structure accessible'], $this->sent[0]->organizationNames);
        self::assertTrue($this->sent[0]->hasMultipleOrganizations);
    }

    #[DataProvider('ineligibleChanges')]
    public function testRevokedRightsPreferencesAndPublicationSkipSending(string $table, string $column, int|string $value): void
    {
        $id = 'session_summary' === $table ? $this->sessionSummaryEntity->getId() : $this->userEntity->getId();
        if ('user_organization_access' === $table) {
            $this->connection->executeStatement('UPDATE user_organization_access SET active = 0 WHERE user_id = ?', [$this->userEntity->getId()]);
        } else {
            $this->connection->update($table, [$column => $value], ['id' => $id]);
        }
        $this->handle();
        $this->handle();
        self::assertSame([], $this->sent);
        self::assertSame('skipped', $this->state()['status']);
    }

    /** @return iterable<string, array{string, string, int|string}> */
    public static function ineligibleChanges(): iterable
    {
        yield 'inactive account' => ['portal_user', 'active', 0];
        yield 'pending account' => ['portal_user', 'status', 'pending'];
        yield 'notifications disabled' => ['portal_user', 'new_session_notifications_enabled', 0];
        yield 'revoked organization accesses' => ['user_organization_access', 'active', 0];
        yield 'unpublished session' => ['session_summary', 'published', 0];
    }

    public function testSmtpFailureIsRecordedAndMessengerRetryCanSucceed(): void
    {
        $this->failSending = true;
        try {
            $this->handle();
            self::fail('Expected SMTP failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('SMTP indisponible', $exception->getMessage());
        }
        self::assertSame('failed', $this->state()['status']);
        self::assertSame('SMTP indisponible', $this->state()['last_error']);
        self::assertSame(1, (int) $this->state()['attempts']);
        self::assertSame([], $this->store->queueableIds());
        $this->failSending = false;
        $this->handle();
        self::assertCount(1, $this->sent);
        self::assertSame('sent', $this->state()['status']);
        self::assertSame(2, (int) $this->state()['attempts']);
    }

    public function testSingleActiveOrganizationHidesStructureNames(): void
    {
        $this->connection->executeStatement('UPDATE user_organization_access SET active = 0 WHERE user_id = ? AND organization_id NOT IN (SELECT organization_id FROM session_summary_organization WHERE session_summary_id = ?)', [$this->userEntity->getId(), $this->sessionSummaryEntity->getId()]);
        $this->handle();
        self::assertCount(1, $this->sent);
        self::assertFalse($this->sent[0]->hasMultipleOrganizations);
    }

    public function testPreferenceChangedByAnotherConnectionWhileLoadingDeliveryIsRespected(): void
    {
        $this->connection->commit();
        $this->fixturesCommitted = true;
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $otherConnection = DriverManager::getConnection($this->connection->getParams());
        self::assertSame($this->connection->getDatabase(), $otherConnection->getDatabase());
        $listener = new class($otherConnection, (int) $this->userEntity->getId()) {
            private bool $changed = false;

            public function __construct(private Connection $otherConnection, private int $userId)
            {
            }

            public function postLoad(PostLoadEventArgs $event): void
            {
                if (!$this->changed && $event->getObject() instanceof SessionNotificationDeliveryEntity) {
                    $this->changed = true;
                    $this->otherConnection->update('portal_user', ['new_session_notifications_enabled' => 0], ['id' => $this->userId]);
                }
            }
        };
        $eventManager = $entityManager->getEventManager();
        $eventManager->addEventListener([Events::postLoad], $listener);
        try {
            $this->handle();
            self::assertSame([], $this->sent);
            self::assertSame('skipped', $this->state()['status']);
        } finally {
            $eventManager->removeEventListener([Events::postLoad], $listener);
            $otherConnection->close();
        }
    }

    private function handle(): void
    {
        (new SendSessionNotificationHandler($this->store, self::getContainer()->get(SessionNotificationRecipientReader::class), new NullLogger(), $this->sender))(new SendSessionNotificationMessage($this->deliveryId));
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        return $this->connection->fetchAssociative('SELECT * FROM session_notification_delivery WHERE id = ?', [$this->deliveryId]) ?: [];
    }
}
