<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Infrastructure\Mailing\DoctrineMailingExecutionLock;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

final class MailingExecutionLockTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testSeparateConnectionsCannotEnterSameLockAndExceptionReleasesIt(): void
    {
        self::bootKernel(['environment' => 'test']);
        $connection = self::getContainer()->get(Connection::class);
        $otherConnection = DriverManager::getConnection($connection->getParams());
        $first = new DoctrineMailingExecutionLock($connection);
        $second = new DoctrineMailingExecutionLock($otherConnection);
        $key = 'test-' . bin2hex(random_bytes(6));
        try {
            $first->run($key, function () use ($second, $key): void {
                try {
                    $second->run($key, static function (): void { self::fail('Concurrent entry'); });
                    self::fail('Expected lock contention');
                } catch (RecoverableMessageHandlingException) {
                    self::assertTrue(true);
                }
                throw new RuntimeException('release');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('release', $exception->getMessage());
        }
        self::assertSame('released', $second->run($key, static fn (): string => 'released'));
        $otherConnection->close();
    }
}
