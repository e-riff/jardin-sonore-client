<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\MailingExecutionLockInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

final readonly class DoctrineMailingExecutionLock implements MailingExecutionLockInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function run(string $key, callable $operation): mixed
    {
        $lockConnection = DriverManager::getConnection($this->connection->getParams());
        $lockName = 'mailing:' . substr(hash('sha256', $key), 0, 48);
        $acquired = false;
        try {
            $acquired = 1 === (int) $lockConnection->fetchOne('SELECT GET_LOCK(?, 0)', [$lockName]);
            if (!$acquired) {
                throw new RecoverableMessageHandlingException('Mailing delivery is busy; retry after the current operation.');
            }

            return $operation();
        } finally {
            if ($acquired) {
                $lockConnection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
            $lockConnection->close();
        }
    }
}
