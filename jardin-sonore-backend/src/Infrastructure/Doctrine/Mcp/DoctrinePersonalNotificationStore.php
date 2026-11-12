<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mcp;

use App\Application\Mcp\PersonalNotificationStore;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use RuntimeException;

final readonly class DoctrinePersonalNotificationStore implements PersonalNotificationStore
{
    public function __construct(private Connection $connection)
    {
    }

    public function reserve(string $requestId, string $fingerprint): array
    {
        $lockName = 'mcp-notify-' . substr(hash('sha256', (string) $this->connection->getDatabase()), 0, 40);
        if (1 !== (int) $this->connection->fetchOne('SELECT GET_LOCK(?, 3)', [$lockName])) {
            throw new RuntimeException('Personal notification reservation is busy.');
        }
        try {
            return $this->connection->transactional(function () use ($requestId, $fingerprint): array {
                $row = $this->connection->fetchAssociative('SELECT fingerprint, state FROM mcp_personal_notification WHERE request_id = ?', [$requestId]);
                if (false !== $row) {
                    return ['status' => hash_equals($row['fingerprint'], $fingerprint) ? $row['state'] : 'conflict', 'isNew' => false];
                }
                $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mcp_personal_notification WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)');
                if (10 <= $count) {
                    return ['status' => 'rate_limited', 'isNew' => false];
                }
                $this->connection->executeStatement(
                    'INSERT INTO mcp_personal_notification (request_id, fingerprint, state, created_at, finished_at) VALUES (?, ?, ?, UTC_TIMESTAMP(), NULL)',
                    [$requestId, $fingerprint, 'sending'],
                );

                return ['status' => 'sending', 'isNew' => true];
            });
        } finally {
            $this->connection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    public function finish(string $requestId, string $status): void
    {
        if (!in_array($status, ['accepted', 'uncertain'], true)) {
            throw new InvalidArgumentException('Invalid notification result.');
        }
        $this->connection->executeStatement('UPDATE mcp_personal_notification SET state = ?, finished_at = UTC_TIMESTAMP() WHERE request_id = ? AND state = ?', [$status, $requestId, 'sending']);
    }
}
