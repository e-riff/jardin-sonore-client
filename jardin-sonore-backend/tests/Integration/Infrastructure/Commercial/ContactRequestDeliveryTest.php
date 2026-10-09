<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Command\DispatchCommercialContactRequestsCommand;
use App\Application\Commercial\CommercialContactMailSenderInterface;
use App\Application\Commercial\CommercialContactRequestInput;
use App\Infrastructure\Commercial\ContactRequestDeliveryStore;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class ContactRequestDeliveryTest extends KernelTestCase
{
    private Connection $connection;
    private ContactRequestDeliveryStore $store;

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $entityManager->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
        $this->store = new ContactRequestDeliveryStore($entityManager, new MockClock('2026-10-09 08:00:00'));
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testReplayedSubmissionCreatesOneRequestAndOneNotification(): void
    {
        $input = $this->input();

        $firstId = $this->store->record($input);
        $secondId = $this->store->record($input);

        self::assertSame($firstId, $secondId);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM commercial_request WHERE submission_key = ?', [$input->submissionKey]));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM commercial_contact_delivery WHERE request_id = ?', [$firstId]));
        $requestEntity = self::getContainer()->get(EntityManagerInterface::class)->find(CommercialRequestEntity::class, $firstId);
        self::assertSame('to_qualify', $requestEntity?->getStatus());
        self::assertSame('Bonjour', $requestEntity?->getMessage());
    }

    public function testReusingAKeyForDifferentContentIsRejected(): void
    {
        $input = $this->input();
        $this->store->record($input);

        $this->expectException(DomainException::class);
        $this->store->record(new CommercialContactRequestInput('Claire Martin', 'claire@example.test', 'Autre demande', $input->submissionKey));
    }

    public function testFailedMailRemainsRetryableAndSuccessfulMailIsNotRepeated(): void
    {
        $requestId = $this->store->record($this->input());
        $deliveryId = (int) $this->connection->fetchOne('SELECT id FROM commercial_contact_delivery WHERE request_id = ?', [$requestId]);
        $attempts = 0;

        try {
            $this->store->deliver($deliveryId, static function () use (&$attempts): void {
                ++$attempts;
                throw new RuntimeException('SMTP indisponible');
            });
            self::fail('The mail sender should fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('SMTP indisponible', $exception->getMessage());
        }

        self::assertSame('failed', $this->connection->fetchOne('SELECT status FROM commercial_contact_delivery WHERE id = ?', [$deliveryId]));
        self::assertSame([$deliveryId], $this->store->queueableIds());
        $this->store->deliver($deliveryId, static function () use (&$attempts): void {
            ++$attempts;
        });
        $this->store->deliver($deliveryId, static function () use (&$attempts): void {
            ++$attempts;
        });

        self::assertSame(2, $attempts);
        self::assertSame('sent', $this->connection->fetchOne('SELECT status FROM commercial_contact_delivery WHERE id = ?', [$deliveryId]));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT attempts FROM commercial_contact_delivery WHERE id = ?', [$deliveryId]));
    }

    public function testDispatcherSendsPendingContactMailOnlyOnce(): void
    {
        $requestId = $this->store->record($this->input());
        $sender = new class implements CommercialContactMailSenderInterface {
            /** @var list<string> */
            public array $messages = [];

            public function send(CommercialRequestEntity $requestEntity): void
            {
                $this->messages[] = $requestEntity->getMessage();
            }
        };
        $commandTester = new CommandTester(new DispatchCommercialContactRequestsCommand($this->store, $sender));

        self::assertSame(0, $commandTester->execute([]));
        self::assertSame(0, $commandTester->execute([]));
        self::assertSame(['Bonjour'], $sender->messages);
        self::assertSame('sent', $this->connection->fetchOne('SELECT status FROM commercial_contact_delivery WHERE request_id = ?', [$requestId]));
    }

    private function input(): CommercialContactRequestInput
    {
        return new CommercialContactRequestInput('Claire Martin', 'claire@example.test', 'Bonjour', sprintf('a3f21d91-5287-4f63-a8ea-%012x', random_int(0, 0xFFFFFFFFFFFF)));
    }
}
