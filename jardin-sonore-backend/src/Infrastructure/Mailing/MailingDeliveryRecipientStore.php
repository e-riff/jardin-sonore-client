<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Domain\Model\Mailing\NewsletterRecipient;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class MailingDeliveryRecipientStore
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param list<NewsletterRecipient> $newsletterRecipients
     */
    public function seedCampaignRecipients(string $campaignUuid, array $newsletterRecipients): void
    {
        $queuedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($newsletterRecipients as $newsletterRecipient) {
            $this->connection->insert('mailing_delivery_recipient', [
                'campaign_uuid' => $campaignUuid,
                'email_address' => $newsletterRecipient->getEmailAddress()->value(),
                'unsubscribe_token' => $newsletterRecipient->getUnsubscribeToken(),
                'display_name' => $newsletterRecipient->getDisplayName(),
                'status' => 'pending',
                'queued_at' => $queuedAt,
                'updated_at' => $queuedAt,
            ], [
                'campaign_uuid' => 'string',
                'email_address' => 'string',
                'unsubscribe_token' => 'string',
                'display_name' => 'string',
                'status' => 'string',
                'queued_at' => 'string',
                'updated_at' => 'string',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function findCampaignRecipientEmailAddresses(string $campaignUuid): array
    {
        /** @var list<string> $emailAddresses */
        $emailAddresses = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(TRIM(email_address))
            FROM mailing_delivery_recipient
            WHERE campaign_uuid = :campaignUuid',
            [
                'campaignUuid' => $campaignUuid,
            ],
        );

        return array_values(array_filter($emailAddresses, static fn (string $emailAddress): bool => '' !== $emailAddress));
    }

    public function countRecentlyDispatched(DateTimeImmutable $since): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM mailing_delivery_recipient WHERE dispatched_at >= :since',
            ['since' => $since->format('Y-m-d H:i:s')],
        );
    }

    /**
     * @return list<string>
     */
    public function findCampaignUuidsWithPendingRecipients(): array
    {
        /** @var list<string> $campaignUuids */
        $campaignUuids = $this->connection->fetchFirstColumn(
            'SELECT campaign_uuid
            FROM mailing_delivery_recipient
            WHERE status = :status
            GROUP BY campaign_uuid
            ORDER BY MIN(queued_at) ASC, MIN(id) ASC',
            ['status' => 'pending'],
        );

        return $campaignUuids;
    }

    /**
     * @return list<array{id:int, campaign_uuid:string, email_address:string, unsubscribe_token:string, display_name:?string}>
     */
    public function claimPendingRecipients(string $campaignUuid, int $limit): array
    {
        if (1 > $limit) {
            return [];
        }

        /** @var list<array{id:int, campaign_uuid:string, email_address:string, unsubscribe_token:string, display_name:?string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, campaign_uuid, email_address, unsubscribe_token, display_name
            FROM mailing_delivery_recipient
            WHERE campaign_uuid = :campaignUuid
            AND status = :status
            ORDER BY queued_at ASC, id ASC
            LIMIT ' . $limit,
            [
                'campaignUuid' => $campaignUuid,
                'status' => 'pending',
            ],
        );

        if ([] === $rows) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->executeStatement(
            'UPDATE mailing_delivery_recipient
            SET status = :status, dispatched_at = :dispatchedAt, updated_at = :updatedAt
            WHERE id IN (:ids)',
            [
                'status' => 'processing',
                'dispatchedAt' => $now,
                'updatedAt' => $now,
                'ids' => $ids,
            ],
            [
                'ids' => ArrayParameterType::INTEGER,
            ],
        );

        return $rows;
    }

    public function markSent(int $deliveryRecipientId): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->update('mailing_delivery_recipient', [
            'status' => 'sent',
            'sent_at' => $now,
            'updated_at' => $now,
            'last_error' => null,
        ], [
            'id' => $deliveryRecipientId,
        ]);
    }

    public function markFailed(int $deliveryRecipientId, string $lastError): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->update('mailing_delivery_recipient', [
            'status' => 'failed',
            'failed_at' => $now,
            'updated_at' => $now,
            'last_error' => mb_substr($lastError, 0, 65535),
        ], [
            'id' => $deliveryRecipientId,
        ]);
    }

    public function cancelPendingRecipients(string $campaignUuid): int
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        return $this->connection->executeStatement(
            'UPDATE mailing_delivery_recipient
            SET status = :status, updated_at = :updatedAt
            WHERE campaign_uuid = :campaignUuid
            AND status = :currentStatus',
            [
                'status' => 'cancelled',
                'updatedAt' => $now,
                'campaignUuid' => $campaignUuid,
                'currentStatus' => 'pending',
            ],
        );
    }

    public function hasOutstandingRecipients(string $campaignUuid): bool
    {
        return 0 < (int) $this->connection->fetchOne(
            'SELECT COUNT(*)
            FROM mailing_delivery_recipient
            WHERE campaign_uuid = :campaignUuid
            AND status IN (:statuses)',
            [
                'campaignUuid' => $campaignUuid,
                'statuses' => ['pending', 'processing'],
            ],
            [
                'statuses' => ArrayParameterType::STRING,
            ],
        );
    }

    public function hasFailedRecipients(string $campaignUuid): bool
    {
        return 0 < (int) $this->connection->fetchOne(
            'SELECT COUNT(*)
            FROM mailing_delivery_recipient
            WHERE campaign_uuid = :campaignUuid
            AND status = :status',
            [
                'campaignUuid' => $campaignUuid,
                'status' => 'failed',
            ],
        );
    }

    /**
     * @return array<string, int>
     */
    public function getCampaignDeliveryCounts(string $campaignUuid): array
    {
        /** @var list<array{status:string, total:string|int}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT status, COUNT(*) AS total
            FROM mailing_delivery_recipient
            WHERE campaign_uuid = :campaignUuid
            GROUP BY status',
            [
                'campaignUuid' => $campaignUuid,
            ],
        );

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function deleteCampaignRecipients(string $campaignUuid): void
    {
        $this->connection->delete('mailing_delivery_recipient', [
            'campaign_uuid' => $campaignUuid,
        ]);
    }
}
