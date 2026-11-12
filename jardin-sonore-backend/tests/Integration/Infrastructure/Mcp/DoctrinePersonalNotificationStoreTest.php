<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mcp;

use App\Infrastructure\Doctrine\Entity\McpPersonalNotificationEntity;
use App\Infrastructure\Doctrine\Mcp\DoctrinePersonalNotificationStore;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class DoctrinePersonalNotificationStoreTest extends KernelTestCase
{
    private Connection $connection;

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel('test', true);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $entityManager->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        // Exercise the real mapping/SQL in a connection-local table without applying a migration.
        foreach ((new SchemaTool($entityManager))->getCreateSchemaSql([$entityManager->getClassMetadata(McpPersonalNotificationEntity::class)]) as $sql) {
            $this->connection->executeStatement(str_replace('CREATE TABLE ', 'CREATE TEMPORARY TABLE ', $sql));
        }
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        parent::tearDown();
    }

    public function testRepeatedReservationAndCompletionCannotReserveTwice(): void
    {
        $store = new DoctrinePersonalNotificationStore($this->connection);
        $fingerprint = str_repeat('a', 64);
        self::assertTrue($store->reserve('daily:1', $fingerprint)['isNew']);
        self::assertSame(['status' => 'sending', 'isNew' => false], $store->reserve('daily:1', $fingerprint));
        $store->finish('daily:1', 'accepted');
        self::assertSame(['status' => 'accepted', 'isNew' => false], $store->reserve('daily:1', $fingerprint));
        self::assertSame(['status' => 'conflict', 'isNew' => false], $store->reserve('daily:1', str_repeat('b', 64)));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mcp_personal_notification'));
    }

    public function testRateLimitCountsOnlyNewRequestsAndExpiresAfterOneHour(): void
    {
        $store = new DoctrinePersonalNotificationStore($this->connection);
        $fingerprint = str_repeat('a', 64);
        for ($index = 0; 10 > $index; ++$index) {
            self::assertTrue($store->reserve("daily:{$index}", $fingerprint)['isNew']);
        }
        self::assertSame(['status' => 'rate_limited', 'isNew' => false], $store->reserve('daily:11', $fingerprint));
        self::assertSame(['status' => 'sending', 'isNew' => false], $store->reserve('daily:0', $fingerprint));
        $this->connection->executeStatement('UPDATE mcp_personal_notification SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR)');
        self::assertTrue($store->reserve('daily:11', $fingerprint)['isNew']);
    }

    public function testConcurrentReservationCannotBypassDatabaseLock(): void
    {
        $otherConnection = DriverManager::getConnection($this->connection->getParams());
        $lockName = 'mcp-notify-' . substr(hash('sha256', (string) $this->connection->getDatabase()), 0, 40);
        self::assertSame(1, (int) $otherConnection->fetchOne('SELECT GET_LOCK(?, 0)', [$lockName]));
        try {
            $this->expectException(RuntimeException::class);
            (new DoctrinePersonalNotificationStore($this->connection))->reserve('daily:1', str_repeat('a', 64));
        } finally {
            $otherConnection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
            $otherConnection->close();
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mcp_personal_notification'));
        }
    }
}
