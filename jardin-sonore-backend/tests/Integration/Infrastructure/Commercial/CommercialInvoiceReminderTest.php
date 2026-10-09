<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Commercial\CreateOverdueInvoiceActions;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialInvoiceReminderTest extends KernelTestCase
{
    private Connection $connection;

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testOldInvoiceEnteredLateReceivesOnlyOneReminder(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Structure rappel');
        $projectEntity = new CommercialProjectEntity('Interventions', $organizationEntity, null, new DateTimeImmutable());
        $projectEntity->setStatus(CommercialProjectEntity::STATUS_CONFIRMED);
        $invoiceEntity = new CommercialInvoiceEntity($projectEntity, 'INV-' . bin2hex(random_bytes(5)), 10000, new DateTimeImmutable('-40 days'));
        $projectEntity->addInvoice($invoiceEntity);
        $entityManager->persist($organizationEntity);
        $entityManager->persist($projectEntity);
        $entityManager->flush();

        $creator = self::getContainer()->get(CreateOverdueInvoiceActions::class);
        self::assertSame(1, $creator->run($invoiceEntity->getId()));
        self::assertSame(0, $creator->run($invoiceEntity->getId()));
        $entityManager->refresh($invoiceEntity);
        self::assertNotNull($invoiceEntity->getReminderAction());
        self::assertCount(1, $projectEntity->getActions());
    }
}
