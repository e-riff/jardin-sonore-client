<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\MailingDeliveryRecipientStoreInterface;
use App\Domain\Model\Mailing\MailingDeliveryRecipientStatus;
use App\Domain\Model\Mailing\NewsletterRecipient;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DoctrineMailingDeliveryRecipientStore implements MailingDeliveryRecipientStoreInterface
{
    private const string TABLE = 'mailing_delivery_recipient';
    private const string TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param list<NewsletterRecipient> $newsletterRecipients
     */
    public function seedCampaignRecipients(string $campaignUuid, array $newsletterRecipients): void
    {
        $queuedAt = $this->currentTimestamp();

        foreach ($newsletterRecipients as $newsletterRecipient) {
            $this->connection->insert(self::TABLE, [
                'campaign_uuid' => $campaignUuid,
                'email_address' => $newsletterRecipient->getEmailAddress()->value(),
                'unsubscribe_token' => $newsletterRecipient->getUnsubscribeToken(),
                'display_name' => $newsletterRecipient->getDisplayName(),
                'status' => MailingDeliveryRecipientStatus::PENDING->value,
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
        $emailAddresses = $this->connection->createQueryBuilder()
            ->select('DISTINCT LOWER(TRIM(email_address))')
            ->from(self::TABLE)
            ->where('campaign_uuid = :campaignUuid')
            ->setParameter('campaignUuid', $campaignUuid)
            ->executeQuery()
            ->fetchFirstColumn();

        return array_values(array_filter($emailAddresses, static fn (string $emailAddress): bool => '' !== $emailAddress));
    }

    public function countRecentlyDispatched(DateTimeImmutable $since): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(self::TABLE)
            ->where('dispatched_at >= :since')
            ->setParameter('since', $since->format(self::TIMESTAMP_FORMAT))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return list<string>
     */
    public function findCampaignUuidsWithPendingRecipients(): array
    {
        /** @var list<string> $campaignUuids */
        $campaignUuids = $this->connection->createQueryBuilder()
            ->select('campaign_uuid')
            ->from(self::TABLE)
            ->where('status = :status')
            ->groupBy('campaign_uuid')
            ->orderBy('MIN(queued_at)', 'ASC')
            ->addOrderBy('MIN(id)', 'ASC')
            ->setParameter('status', MailingDeliveryRecipientStatus::PENDING->value)
            ->executeQuery()
            ->fetchFirstColumn();

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
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'campaign_uuid', 'email_address', 'unsubscribe_token', 'display_name')
            ->from(self::TABLE)
            ->where('campaign_uuid = :campaignUuid')
            ->andWhere('status = :status')
            ->orderBy('queued_at', 'ASC')
            ->addOrderBy('id', 'ASC')
            ->setMaxResults($limit)
            ->setParameter('campaignUuid', $campaignUuid)
            ->setParameter('status', MailingDeliveryRecipientStatus::PENDING->value)
            ->executeQuery()
            ->fetchAllAssociative();

        if ([] === $rows) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $now = $this->currentTimestamp();

        $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . '
            SET status = :status, dispatched_at = :dispatchedAt, updated_at = :updatedAt
            WHERE id IN (:ids)',
            [
                'status' => MailingDeliveryRecipientStatus::PROCESSING->value,
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
        $now = $this->currentTimestamp();

        $this->connection->update(self::TABLE, [
            'status' => MailingDeliveryRecipientStatus::SENT->value,
            'sent_at' => $now,
            'updated_at' => $now,
            'last_error' => null,
        ], [
            'id' => $deliveryRecipientId,
        ]);
    }

    public function markFailed(int $deliveryRecipientId, string $lastError): void
    {
        $now = $this->currentTimestamp();

        $this->connection->update(self::TABLE, [
            'status' => MailingDeliveryRecipientStatus::FAILED->value,
            'failed_at' => $now,
            'updated_at' => $now,
            'last_error' => mb_substr($lastError, 0, 65535),
        ], [
            'id' => $deliveryRecipientId,
        ]);
    }

    public function cancelPendingRecipients(string $campaignUuid): int
    {
        $now = $this->currentTimestamp();

        return $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . '
            SET status = :status, updated_at = :updatedAt
            WHERE campaign_uuid = :campaignUuid
            AND status = :currentStatus',
            [
                'status' => MailingDeliveryRecipientStatus::CANCELLED->value,
                'updatedAt' => $now,
                'campaignUuid' => $campaignUuid,
                'currentStatus' => MailingDeliveryRecipientStatus::PENDING->value,
            ],
        );
    }

    public function hasOutstandingRecipients(string $campaignUuid): bool
    {
        return 0 < (int) $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(self::TABLE)
            ->where('campaign_uuid = :campaignUuid')
            ->andWhere('status IN (:statuses)')
            ->setParameter('campaignUuid', $campaignUuid)
            ->setParameter('statuses', [
                MailingDeliveryRecipientStatus::PENDING->value,
                MailingDeliveryRecipientStatus::PROCESSING->value,
            ], ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchOne();
    }

    public function hasFailedRecipients(string $campaignUuid): bool
    {
        return 0 < (int) $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(self::TABLE)
            ->where('campaign_uuid = :campaignUuid')
            ->andWhere('status = :status')
            ->setParameter('campaignUuid', $campaignUuid)
            ->setParameter('status', MailingDeliveryRecipientStatus::FAILED->value)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return array<string, int>
     */
    public function getCampaignDeliveryCounts(string $campaignUuid): array
    {
        /** @var list<array{status:string, total:string|int}> $rows */
        $rows = $this->connection->createQueryBuilder()
            ->select('status', 'COUNT(*) AS total')
            ->from(self::TABLE)
            ->where('campaign_uuid = :campaignUuid')
            ->groupBy('status')
            ->setParameter('campaignUuid', $campaignUuid)
            ->executeQuery()
            ->fetchAllAssociative();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function deleteCampaignRecipients(string $campaignUuid): void
    {
        $this->connection->delete(self::TABLE, [
            'campaign_uuid' => $campaignUuid,
        ]);
    }

    private function currentTimestamp(): string
    {
        return (new DateTimeImmutable())->format(self::TIMESTAMP_FORMAT);
    }
}
