<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mcp;

use App\Application\Mcp\DraftReceipt;
use App\Application\Mcp\MailDraftReceiptStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use RuntimeException;

final readonly class DoctrineMailDraftReceiptStore implements MailDraftReceiptStore
{
    public function __construct(private Connection $connection)
    {
    }

    public function reserve(string $sourceKey, string $draftMessageId, string $folder): DraftReceipt
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $inserted = $this->connection->executeStatement(
            'INSERT IGNORE INTO mcp_mail_draft (source_key, draft_message_id, folder, imap_uid, state, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?, ?)',
            [$sourceKey, $draftMessageId, $folder, 'reserved', $now, $now],
        );
        $row = $this->connection->fetchAssociative('SELECT source_key, draft_message_id, folder, imap_uid, state, pending_message_id FROM mcp_mail_draft WHERE source_key = ?', [$sourceKey]);
        if (false === $row) {
            throw new RuntimeException('Draft reservation failed.');
        }

        return new DraftReceipt(
            $row['source_key'],
            $row['draft_message_id'],
            $row['folder'],
            $row['state'],
            1 === $inserted,
            null === $row['imap_uid'] ? null : (int) $row['imap_uid'],
            $row['pending_message_id'],
        );
    }

    public function find(string $sourceKey): ?DraftReceipt
    {
        $row = $this->connection->fetchAssociative('SELECT source_key, draft_message_id, folder, imap_uid, state, pending_message_id FROM mcp_mail_draft WHERE source_key = ?', [$sourceKey]);
        if (false === $row) {
            return null;
        }

        return new DraftReceipt(
            $row['source_key'],
            $row['draft_message_id'],
            $row['folder'],
            $row['state'],
            false,
            null === $row['imap_uid'] ? null : (int) $row['imap_uid'],
            $row['pending_message_id'],
        );
    }

    public function beginUpdate(string $sourceKey, string $currentMessageId, string $pendingMessageId): bool
    {
        return 1 === $this->connection->executeStatement(
            'UPDATE mcp_mail_draft SET state = ?, pending_message_id = ?, updated_at = ? WHERE source_key = ? AND draft_message_id = ? AND state = ?',
            ['updating', $pendingMessageId, (new DateTimeImmutable())->format('Y-m-d H:i:s'), $sourceKey, $currentMessageId, 'saved'],
        );
    }

    public function completeUpdate(string $sourceKey, string $pendingMessageId, int $uid): bool
    {
        return 1 === $this->connection->executeStatement(
            'UPDATE mcp_mail_draft SET draft_message_id = ?, pending_message_id = NULL, imap_uid = ?, state = ?, updated_at = ? WHERE source_key = ? AND pending_message_id = ? AND state = ?',
            [$pendingMessageId, $uid, 'saved', (new DateTimeImmutable())->format('Y-m-d H:i:s'), $sourceKey, $pendingMessageId, 'updating'],
        );
    }

    public function markSaved(string $sourceKey, ?int $uid): void
    {
        $this->connection->executeStatement(
            'UPDATE mcp_mail_draft SET state = ?, imap_uid = ?, updated_at = ? WHERE source_key = ? AND state <> ?',
            ['saved', $uid, (new DateTimeImmutable())->format('Y-m-d H:i:s'), $sourceKey, 'updating'],
        );
    }

    public function markUncertain(string $sourceKey): void
    {
        $this->connection->executeStatement(
            'UPDATE mcp_mail_draft SET state = ?, updated_at = ? WHERE source_key = ? AND state <> ?',
            ['uncertain', (new DateTimeImmutable())->format('Y-m-d H:i:s'), $sourceKey, 'saved'],
        );
    }
}
