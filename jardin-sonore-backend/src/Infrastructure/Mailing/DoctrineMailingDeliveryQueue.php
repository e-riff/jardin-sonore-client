<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\MailingDeliveryQueueInterface;
use App\Application\Mailing\MailingQueuedRecipient;
use App\Domain\Model\Mailing\MailingDeliveryRecipientStatus;
use App\Domain\Model\Mailing\MailingRecipientOrganizationSnapshot;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMailingDeliveryQueue implements MailingDeliveryQueueInterface
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
            $deliveryRecipientId = (int) $this->connection->lastInsertId();
            foreach ($newsletterRecipient->getOrganizationSnapshots() as $snapshot) {
                $this->connection->insert('mailing_delivery_recipient_organization', [
                    'delivery_recipient_id' => $deliveryRecipientId,
                    'organization_uuid' => $snapshot->organizationUuid->toBinary(),
                    'organization_name' => $snapshot->organizationName,
                    'organization_type' => $snapshot->organizationType,
                    'municipality_insee_code' => $snapshot->municipalityInseeCode,
                ], ['organization_uuid' => ParameterType::BINARY]);
            }
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
        return $this->connection->transactional(fn (): array => $this->claimLockedPendingRecipients($campaignUuid, $limit));
    }

    /** @return list<array{id:int, campaign_uuid:string, email_address:string, unsubscribe_token:string, display_name:?string}> */
    private function claimLockedPendingRecipients(string $campaignUuid, int $limit): array
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
            ->forUpdate()
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
            WHERE id IN (:ids) AND status = :pendingStatus',
            [
                'status' => MailingDeliveryRecipientStatus::PROCESSING->value,
                'dispatchedAt' => $now,
                'updatedAt' => $now,
                'ids' => $ids,
                'pendingStatus' => MailingDeliveryRecipientStatus::PENDING->value,
            ],
            [
                'ids' => ArrayParameterType::INTEGER,
            ],
        );

        return $rows;
    }

    public function findDeliveryRecipient(int $id): ?MailingQueuedRecipient
    {
        $row = $this->connection->fetchAssociative('SELECT campaign_uuid, status, email_address, unsubscribe_token, display_name FROM mailing_delivery_recipient WHERE id = ?', [$id]);
        if (false === $row) {
            return null;
        }
        $snapshots = [];
        foreach ($this->connection->fetchAllAssociative('SELECT HEX(organization_uuid) AS uuid, organization_name, organization_type, municipality_insee_code FROM mailing_delivery_recipient_organization WHERE delivery_recipient_id = ?', [$id]) as $snapshot) {
            $snapshots[] = new MailingRecipientOrganizationSnapshot(
                Uuid::fromBinary((string) hex2bin((string) $snapshot['uuid'])),
                (string) $snapshot['organization_name'],
                null === $snapshot['organization_type'] ? null : (string) $snapshot['organization_type'],
                null === $snapshot['municipality_insee_code'] ? null : (string) $snapshot['municipality_insee_code'],
            );
        }

        return new MailingQueuedRecipient((string) $row['campaign_uuid'], (string) $row['status'], new NewsletterRecipient(
            new EmailAddress((string) $row['email_address']), (string) $row['unsubscribe_token'],
            null === $row['display_name'] ? null : (string) $row['display_name'], $snapshots,
        ));
    }

    public function markSending(int $deliveryRecipientId): void
    {
        $this->connection->update(self::TABLE, ['status' => MailingDeliveryRecipientStatus::SENDING->value, 'updated_at' => $this->currentTimestamp()], ['id' => $deliveryRecipientId]);
    }

    public function markUncertain(int $deliveryRecipientId, string $error): void
    {
        $this->connection->update(self::TABLE, ['status' => MailingDeliveryRecipientStatus::UNCERTAIN->value, 'last_error' => mb_substr($error, 0, 65535), 'updated_at' => $this->currentTimestamp()], ['id' => $deliveryRecipientId]);
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

    public function markCancelled(int $deliveryRecipientId, ?string $reason = null): void
    {
        $this->connection->update(self::TABLE, [
            'status' => MailingDeliveryRecipientStatus::CANCELLED->value,
            'cancellation_reason' => $reason,
            'updated_at' => $this->currentTimestamp(),
            'last_error' => null,
        ], ['id' => $deliveryRecipientId]);
    }

    public function cancelPendingRecipients(string $campaignUuid): int
    {
        $now = $this->currentTimestamp();

        return $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . '
            SET status = :status, updated_at = :updatedAt, cancellation_reason = :reason
            WHERE campaign_uuid = :campaignUuid
            AND status = :currentStatus',
            [
                'status' => MailingDeliveryRecipientStatus::CANCELLED->value,
                'updatedAt' => $now,
                'campaignUuid' => $campaignUuid,
                'currentStatus' => MailingDeliveryRecipientStatus::PENDING->value,
                'reason' => 'campaign_stopped',
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
                MailingDeliveryRecipientStatus::SENDING->value,
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
            ->andWhere('status IN (:statuses)')
            ->setParameter('campaignUuid', $campaignUuid)
            ->setParameter('statuses', [MailingDeliveryRecipientStatus::FAILED->value, MailingDeliveryRecipientStatus::UNCERTAIN->value], ArrayParameterType::STRING)
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
