<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mcp;

use App\Infrastructure\Doctrine\Mcp\DoctrineMailDraftReceiptStore;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class DoctrineMailDraftReceiptStoreTest extends KernelTestCase
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

    public function testReservesOnlyOneReceiptForTheSameSource(): void
    {
        $store = new DoctrineMailDraftReceiptStore($this->connection);
        $sourceKey = str_repeat('f', 64);

        $first = $store->reserve($sourceKey, '<draft-1@example.test>', 'Drafts');
        $second = $store->reserve($sourceKey, '<draft-2@example.test>', 'Drafts');

        self::assertTrue($first->isNew);
        self::assertFalse($second->isNew);
        self::assertSame('<draft-1@example.test>', $second->messageId);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mcp_mail_draft WHERE source_key = ?', [$sourceKey]));
    }
}
