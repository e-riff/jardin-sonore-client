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
        self::assertSame(CommercialRequestEntity::STATUS_QUALIFIED, $requestEntity?->getStatus());
        self::assertSame('Bonjour', $requestEntity?->getMessage());
        $projects = $this->connection->fetchAllAssociative('SELECT id, title, status, organization_id FROM commercial_project WHERE request_id = ?', [$firstId]);
        self::assertCount(1, $projects);
        self::assertSame('Claire Martin', $projects[0]['title']);
        self::assertSame('discussion', $projects[0]['status']);
        self::assertNull($projects[0]['organization_id']);
        $actions = $this->connection->fetchAllAssociative('SELECT title, due_on, status FROM commercial_action WHERE project_id = ?', [(int) $projects[0]['id']]);
        self::assertCount(1, $actions);
        self::assertSame('Répondre', $actions[0]['title']);
        self::assertSame('2026-10-09', $actions[0]['due_on']);
        self::assertSame('open', $actions[0]['status']);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM commercial_event WHERE project_id = ?', [(int) $projects[0]['id']]));
    }

    public function testIntakePreservesRawContactFieldsWithoutCreatingAnOrganization(): void
    {
        $input = new CommercialContactRequestInput('Claire Martin', 'claire@example.test', 'Ateliers demandés', $this->input()->submissionKey, 'Crèche Les Mésanges', 'Nantes', '06 12 34 56 78');

        $requestId = $this->store->record($input);

        $request = $this->connection->fetchAssociative('SELECT sender_name, email_address, message, organization_name, city, phone, organization_id FROM commercial_request WHERE id = ?', [$requestId]);
        self::assertSame('Claire Martin', $request['sender_name']);
        self::assertSame('claire@example.test', $request['email_address']);
        self::assertSame('Ateliers demandés', $request['message']);
        self::assertSame('Crèche Les Mésanges', $request['organization_name']);
        self::assertSame('Nantes', $request['city']);
        self::assertSame('06 12 34 56 78', $request['phone']);
        self::assertNull($request['organization_id']);
        self::assertSame('Crèche Les Mésanges', $this->connection->fetchOne('SELECT title FROM commercial_project WHERE request_id = ?', [$requestId]));
    }

    public function testReminderUsesTheLocalIntakeDate(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $store = new ContactRequestDeliveryStore($entityManager, new MockClock('2026-10-09 23:30:00 UTC'));

        $requestId = $store->record($this->input());

        self::assertSame('2026-10-10', $this->connection->fetchOne('SELECT action.due_on FROM commercial_action action JOIN commercial_project project ON project.id = action.project_id WHERE project.request_id = ?', [$requestId]));
    }

    public function testConflictingLegacyDeliveryLeavesItsRequestWithoutAProject(): void
    {
        $input = $this->input();
        $this->connection->insert('commercial_request', [
            'source' => 'site',
            'sender_name' => $input->name,
            'email_address' => $input->emailAddress,
            'message' => $input->message,
            'received_at' => '2026-10-09 08:00:00',
            'submission_key' => $input->submissionKey,
            'status' => CommercialRequestEntity::STATUS_TO_QUALIFY,
        ]);
        $requestId = (int) $this->connection->lastInsertId();
        $this->connection->insert('commercial_contact_delivery', [
            'request_id' => $requestId,
            'payload_hash' => str_repeat('a', 64),
            'status' => 'pending',
            'created_at' => '2026-10-09 08:00:00',
            'attempts' => 0,
        ]);

        try {
            $this->store->record($input);
            self::fail('The conflicting delivery should be rejected.');
        } catch (DomainException) {
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM commercial_project WHERE request_id = ?', [$requestId]));
            self::assertSame(CommercialRequestEntity::STATUS_TO_QUALIFY, $this->connection->fetchOne('SELECT status FROM commercial_request WHERE id = ?', [$requestId]));
        }
    }

    public function testReusingAKeyForDifferentContentIsRejected(): void
    {
        $input = $this->input();
        $this->store->record($input);

        try {
            $this->store->record(new CommercialContactRequestInput('Claire Martin', 'claire@example.test', 'Autre demande', $input->submissionKey));
            self::fail('A changed replay should be rejected.');
        } catch (DomainException) {
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM commercial_project WHERE request_id = (SELECT id FROM commercial_request WHERE submission_key = ?)', [$input->submissionKey]));
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM commercial_action WHERE project_id IN (SELECT id FROM commercial_project WHERE request_id = (SELECT id FROM commercial_request WHERE submission_key = ?))', [$input->submissionKey]));
            self::assertSame('Bonjour', $this->connection->fetchOne('SELECT message FROM commercial_request WHERE submission_key = ?', [$input->submissionKey]));
        }
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
