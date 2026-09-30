<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Session;

use App\Application\Session\SaveSessionSummaryInput;
use App\Application\Session\SetSessionPublication;
use App\Application\Session\UpdateSessionSummary;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\SessionNotificationDeliveryEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use App\Infrastructure\Doctrine\Mapper\OrganizationMapper;
use App\Infrastructure\Doctrine\Repository\SessionSummaryDoctrineRepository;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

final class SessionNotificationSchedulingTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private bool $fixturesCommitted = false;
    /** @var list<SessionSummaryEntity> */
    private array $sessions = [];
    /** @var list<UserEntity> */
    private array $users = [];
    /** @var list<OrganizationEntity> */
    private array $organizations = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        if ($this->fixturesCommitted) {
            foreach ($this->sessions as $sessionSummaryEntity) {
                $this->connection->delete('session_summary', ['id' => $sessionSummaryEntity->getId()]);
            }
            foreach ($this->users as $userEntity) {
                $this->connection->delete('portal_user', ['id' => $userEntity->getId()]);
            }
            foreach ($this->organizations as $organizationEntity) {
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

    public function testBothPublicationPathsScheduleSameEligibleRecipients(): void
    {
        $organizationEntity = $this->organization();
        $userEntity = $this->user([$organizationEntity]);
        $buttonSession = $this->session([$organizationEntity]);
        $formSession = $this->session([$organizationEntity]);

        self::getContainer()->get(SetSessionPublication::class)($buttonSession->getUuid(), true);
        self::getContainer()->get(UpdateSessionSummary::class)($formSession->getUuid(), new SaveSessionSummaryInput(
            title: $formSession->getTitle(), sessionDate: $formSession->getSessionDate(),
            organizations: [(new OrganizationMapper())->toDomain($organizationEntity)],
            theme: null, generalNotes: null, materialSummary: null, furtherExploration: null,
            instrumentUuids: [], recommendationUuids: [], published: true,
        ));

        self::assertSame([$userEntity->getId()], $this->recipientIds($buttonSession));
        self::assertSame([$userEntity->getId()], $this->recipientIds($formSession));
        self::assertSame(['pending'], $this->connection->fetchFirstColumn('SELECT status FROM session_notification_delivery WHERE session_summary_id = ?', [$buttonSession->getId()]));
    }

    public function testMultipleStructuresProduceOneDeliveryPerUser(): void
    {
        $firstOrganization = $this->organization();
        $secondOrganization = $this->organization();
        $userEntity = $this->user([$firstOrganization, $secondOrganization]);
        $sessionSummaryEntity = $this->session([$firstOrganization, $secondOrganization]);

        self::getContainer()->get(SetSessionPublication::class)($sessionSummaryEntity->getUuid(), true);

        self::assertSame([$userEntity->getId()], $this->recipientIds($sessionSummaryEntity));
    }

    public function testInactivePendingAndOptedOutUsersAreExcluded(): void
    {
        $organizationEntity = $this->organization();
        $eligibleUser = $this->user([$organizationEntity]);
        $this->user([$organizationEntity], active: false);
        $this->user([$organizationEntity], status: UserStatus::PENDING);
        $this->user([$organizationEntity], status: UserStatus::INACTIVE);
        $this->user([$organizationEntity], notificationsEnabled: false);
        $this->user([$organizationEntity], accessActive: false);
        $this->user([$this->organization()]);
        $sessionSummaryEntity = $this->session([$organizationEntity]);

        self::getContainer()->get(SetSessionPublication::class)($sessionSummaryEntity->getUuid(), true);

        self::assertSame([$eligibleUser->getId()], $this->recipientIds($sessionSummaryEntity));
    }

    public function testPublicationWithoutRecipientsStillConsumesFirstPublication(): void
    {
        $organizationEntity = $this->organization();
        $sessionSummaryEntity = $this->session([$organizationEntity]);
        $setSessionPublication = self::getContainer()->get(SetSessionPublication::class);
        $setSessionPublication($sessionSummaryEntity->getUuid(), true);
        self::assertNotNull($this->firstPublishedAt($sessionSummaryEntity));
        $this->user([$organizationEntity]);

        $setSessionPublication($sessionSummaryEntity->getUuid(), false);
        $setSessionPublication($sessionSummaryEntity->getUuid(), true);

        self::assertSame([], $this->recipientIds($sessionSummaryEntity));
    }

    public function testRepeatedPublicationAndStaleObjectsDoNotReschedule(): void
    {
        $organizationEntity = $this->organization();
        $firstUser = $this->user([$organizationEntity]);
        $sessionSummaryEntity = $this->session([$organizationEntity]);
        $sessionSummaryRepository = self::getContainer()->get(SessionSummaryDoctrineRepository::class);
        $staleSession = $sessionSummaryRepository->findByUuid($sessionSummaryEntity->getUuid());
        self::assertNotNull($staleSession);
        $setSessionPublication = self::getContainer()->get(SetSessionPublication::class);
        $setSessionPublication($sessionSummaryEntity->getUuid(), true);
        $firstPublishedAt = '2026-09-25 10:00:00';
        $this->connection->update('session_summary', ['first_published_at' => $firstPublishedAt], ['id' => $sessionSummaryEntity->getId()]);
        $this->user([$organizationEntity]);

        $staleSession->setPublished(true);
        $sessionSummaryRepository->save($staleSession, false);
        $setSessionPublication($sessionSummaryEntity->getUuid(), false);
        $setSessionPublication($sessionSummaryEntity->getUuid(), true);

        self::assertSame([$firstUser->getId()], $this->recipientIds($sessionSummaryEntity));
        self::assertSame($firstPublishedAt, $this->firstPublishedAt($sessionSummaryEntity));
    }

    public function testConcurrentPublicationDoesNotReschedule(): void
    {
        $organizationEntity = $this->organization();
        $userEntity = $this->user([$organizationEntity]);
        $sessionSummaryEntity = $this->session([$organizationEntity]);
        $this->connection->commit();
        $this->fixturesCommitted = true;
        $script = <<<'PHP'
            require 'tests/bootstrap.php';
            $kernel = new class('test', true) extends App\Kernel {
                public function getCacheDir(): string { return '/tmp/jardin-sonore-session-notification-test-cache'; }
                public function getProjectDir(): string { return (string) getcwd(); }
            };
            $kernel->boot();
            $repository = $kernel->getContainer()->get('test.service_container')->get(App\Infrastructure\Doctrine\Repository\SessionSummaryDoctrineRepository::class);
            $session = $repository->findByUuid(Symfony\Component\Uid\Uuid::fromString($argv[1]));
            if (null === $session || null !== $session->getFirstPublishedAt()) { throw new RuntimeException('Expected unpublished fixture.'); }
            echo "loaded\n";
            fflush(STDOUT);
            fgets(STDIN);
            $session->setPublished(true);
            $repository->save($session, false);
            PHP;
        $processes = [];
        $inputs = [];
        $loaded = [false, false];
        $outputs = ['', ''];
        try {
            for ($index = 0; 2 > $index; ++$index) {
                $input = new InputStream();
                $process = new Process([PHP_BINARY, '-r', $script, $sessionSummaryEntity->getUuid()->toRfc4122()], dirname(__DIR__, 4));
                $process->setTimeout(20)->setInput($input);
                $inputs[] = $input;
                $processes[] = $process;
                $process->start(static function (string $type, string $output) use ($index, &$loaded, &$outputs): void {
                    if (Process::OUT === $type) {
                        $outputs[$index] .= $output;
                        $loaded[$index] = str_contains($outputs[$index], "loaded\n");
                    }
                });
            }
            $deadline = microtime(true) + 20;
            while (!$loaded[0] || !$loaded[1]) {
                foreach ($processes as $index => $process) {
                    $running = $process->isRunning();
                    if (!$running && !$loaded[$index]) {
                        self::fail($process->getOutput() . $process->getErrorOutput() . ' exit=' . $process->getExitCode());
                    }
                }
                if (microtime(true) >= $deadline) {
                    self::fail('Publication workers did not reach the barrier: ' . implode("\n", $outputs));
                }
                usleep(10000);
            }
            self::assertSame([true, true], $loaded);
            foreach ($inputs as $input) {
                $input->write("go\n");
                $input->close();
            }
            foreach ($processes as $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput());
            }
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
        }

        self::assertSame([$userEntity->getId()], $this->recipientIds($sessionSummaryEntity));
        self::assertNotNull($this->firstPublishedAt($sessionSummaryEntity));
    }

    public function testSchedulingFailureRollsBackPublication(): void
    {
        $organizationEntity = $this->organization();
        $this->user([$organizationEntity]);
        $sessionSummaryEntity = $this->session([$organizationEntity]);
        $listener = new class {
            public function onFlush(OnFlushEventArgs $event): void
            {
                foreach ($event->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
                    if ($entity instanceof SessionNotificationDeliveryEntity) {
                        throw new RuntimeException('Scheduling unavailable.');
                    }
                }
            }
        };
        $this->entityManager->getEventManager()->addEventListener([Events::onFlush], $listener);
        try {
            self::getContainer()->get(SetSessionPublication::class)($sessionSummaryEntity->getUuid(), true);
            self::fail('Scheduling failure should abort publication.');
        } catch (RuntimeException $exception) {
            self::assertSame('Scheduling unavailable.', $exception->getMessage());
        } finally {
            $this->entityManager->getEventManager()->removeEventListener([Events::onFlush], $listener);
        }

        self::assertSame(0, (int) $this->connection->fetchOne('SELECT published FROM session_summary WHERE id = ?', [$sessionSummaryEntity->getId()]));
        self::assertNull($this->firstPublishedAt($sessionSummaryEntity));
        self::assertSame([], $this->recipientIds($sessionSummaryEntity));
    }

    public function testStaleDocumentSaveDoesNotUndoPublication(): void
    {
        $organizationEntity = $this->organization();
        $userEntity = $this->user([$organizationEntity]);
        $sessionSummaryEntity = $this->session([$organizationEntity]);
        $sessionSummaryRepository = self::getContainer()->get(SessionSummaryDoctrineRepository::class);
        $documentSession = $sessionSummaryRepository->findByUuid($sessionSummaryEntity->getUuid());
        self::assertNotNull($documentSession);
        self::getContainer()->get(SetSessionPublication::class)($sessionSummaryEntity->getUuid(), true);

        $documentSession->markDocumentReady('/tmp/notification-test.pdf');
        $sessionSummaryRepository->save($documentSession, false);

        self::assertSame(1, (int) $this->connection->fetchOne('SELECT published FROM session_summary WHERE id = ?', [$sessionSummaryEntity->getId()]));
        self::assertSame([$userEntity->getId()], $this->recipientIds($sessionSummaryEntity));
    }

    public function testStaleDocumentSaveDoesNotRepublishSession(): void
    {
        $organizationEntity = $this->organization();
        $userEntity = $this->user([$organizationEntity]);
        $sessionSummaryEntity = $this->session([$organizationEntity]);
        $setSessionPublication = self::getContainer()->get(SetSessionPublication::class);
        $setSessionPublication($sessionSummaryEntity->getUuid(), true);
        $sessionSummaryRepository = self::getContainer()->get(SessionSummaryDoctrineRepository::class);
        $documentSession = $sessionSummaryRepository->findByUuid($sessionSummaryEntity->getUuid());
        self::assertNotNull($documentSession);
        $setSessionPublication($sessionSummaryEntity->getUuid(), false);

        $documentSession->markDocumentReady('/tmp/notification-test.pdf');
        $sessionSummaryRepository->save($documentSession, false);

        self::assertSame(0, (int) $this->connection->fetchOne('SELECT published FROM session_summary WHERE id = ?', [$sessionSummaryEntity->getId()]));
        self::assertSame([$userEntity->getId()], $this->recipientIds($sessionSummaryEntity));
    }

    public function testPublishedSessionWithoutStructuresNotifiesWhenFirstAttached(): void
    {
        $organizationEntity = $this->organization();
        $userEntity = $this->user([$organizationEntity]);
        $sessionSummaryEntity = $this->session([]);
        self::getContainer()->get(SetSessionPublication::class)($sessionSummaryEntity->getUuid(), true);
        self::assertSame([], $this->recipientIds($sessionSummaryEntity));

        $this->updateOrganizations($sessionSummaryEntity, [$organizationEntity], true);

        self::assertSame([$userEntity->getId()], $this->recipientIds($sessionSummaryEntity));
    }

    public function testPublishedSessionNotifiesOnlyNewStructureUsersAndNeverDuplicatesAUser(): void
    {
        $firstOrganizationEntity = $this->organization();
        $secondOrganizationEntity = $this->organization();
        $firstUserEntity = $this->user([$firstOrganizationEntity]);
        $secondUserEntity = $this->user([$secondOrganizationEntity]);
        $sharedUserEntity = $this->user([$firstOrganizationEntity, $secondOrganizationEntity]);
        $sessionSummaryEntity = $this->session([$firstOrganizationEntity]);
        self::getContainer()->get(SetSessionPublication::class)($sessionSummaryEntity->getUuid(), true);

        $this->updateOrganizations($sessionSummaryEntity, [$firstOrganizationEntity, $secondOrganizationEntity], true);

        $expectedIds = [$firstUserEntity->getId(), $secondUserEntity->getId(), $sharedUserEntity->getId()];
        sort($expectedIds);
        self::assertSame($expectedIds, $this->recipientIds($sessionSummaryEntity));
        $thirdUserEntity = $this->user([$firstOrganizationEntity]);
        $this->updateOrganizations($sessionSummaryEntity, [$secondOrganizationEntity], true);
        $this->updateOrganizations($sessionSummaryEntity, [$firstOrganizationEntity, $secondOrganizationEntity], true);
        self::assertSame($expectedIds, $this->recipientIds($sessionSummaryEntity));
        self::assertNotContains($thirdUserEntity->getId(), $this->recipientIds($sessionSummaryEntity));
    }

    public function testNewStructureAttachedWhileUnpublishedIsNotifiedOnlyUponPublication(): void
    {
        $firstOrganizationEntity = $this->organization();
        $secondOrganizationEntity = $this->organization();
        $firstUserEntity = $this->user([$firstOrganizationEntity]);
        $secondUserEntity = $this->user([$secondOrganizationEntity]);
        $sessionSummaryEntity = $this->session([$firstOrganizationEntity]);
        $setSessionPublication = self::getContainer()->get(SetSessionPublication::class);
        $setSessionPublication($sessionSummaryEntity->getUuid(), true);
        $setSessionPublication($sessionSummaryEntity->getUuid(), false);

        $this->updateOrganizations($sessionSummaryEntity, [$firstOrganizationEntity, $secondOrganizationEntity], false);
        self::assertSame([$firstUserEntity->getId()], $this->recipientIds($sessionSummaryEntity));
        $setSessionPublication($sessionSummaryEntity->getUuid(), true);

        self::assertSame([$firstUserEntity->getId(), $secondUserEntity->getId()], $this->recipientIds($sessionSummaryEntity));
    }

    /** @param list<OrganizationEntity> $organizationEntities */
    private function updateOrganizations(SessionSummaryEntity $sessionSummaryEntity, array $organizationEntities, bool $published): void
    {
        $organizationMapper = new OrganizationMapper();
        self::getContainer()->get(UpdateSessionSummary::class)($sessionSummaryEntity->getUuid(), new SaveSessionSummaryInput(
            title: $sessionSummaryEntity->getTitle(), sessionDate: $sessionSummaryEntity->getSessionDate(),
            organizations: array_map($organizationMapper->toDomain(...), $organizationEntities),
            theme: null, generalNotes: null, materialSummary: null, furtherExploration: null,
            instrumentUuids: [], recommendationUuids: [], published: $published,
        ));
    }

    private function organization(): OrganizationEntity
    {
        $organizationEntity = (new OrganizationEntity())->setName('Test notifications ' . bin2hex(random_bytes(8)));
        $this->entityManager->persist($organizationEntity);
        $this->entityManager->flush();
        $this->organizations[] = $organizationEntity;

        return $organizationEntity;
    }

    /** @param list<OrganizationEntity> $organizations */
    private function user(array $organizations, bool $active = true, UserStatus $status = UserStatus::ACTIVE, bool $notificationsEnabled = true, bool $accessActive = true): UserEntity
    {
        $userEntity = (new UserEntity())->setEmail('notification-' . bin2hex(random_bytes(8)) . '@portal.test')
            ->setStatus($status)->setActive($active)->setPassword('unused')->setNewSessionNotificationsEnabled($notificationsEnabled);
        foreach ($organizations as $organizationEntity) {
            $userEntity->addOrganizationAccess((new UserOrganizationAccessEntity())->setOrganization($organizationEntity)->setActive($accessActive));
        }
        $this->entityManager->persist($userEntity);
        $this->entityManager->flush();
        $this->users[] = $userEntity;

        return $userEntity;
    }

    /** @param list<OrganizationEntity> $organizations */
    private function session(array $organizations): SessionSummaryEntity
    {
        $sessionSummaryEntity = (new SessionSummaryEntity())->setTitle('Séance notifications ' . bin2hex(random_bytes(8)))->replaceOrganizations($organizations);
        $this->entityManager->persist($sessionSummaryEntity);
        $this->entityManager->flush();
        $this->sessions[] = $sessionSummaryEntity;

        return $sessionSummaryEntity;
    }

    /** @return list<int> */
    private function recipientIds(SessionSummaryEntity $sessionSummaryEntity): array
    {
        return array_map(intval(...), $this->connection->fetchFirstColumn('SELECT user_id FROM session_notification_delivery WHERE session_summary_id = ? ORDER BY user_id', [$sessionSummaryEntity->getId()]));
    }

    private function firstPublishedAt(SessionSummaryEntity $sessionSummaryEntity): ?string
    {
        $firstPublishedAt = $this->connection->fetchOne('SELECT first_published_at FROM session_summary WHERE id = ?', [$sessionSummaryEntity->getId()]);

        return false === $firstPublishedAt ? null : $firstPublishedAt;
    }
}
