<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\Query\OutreachJournalFilter;
use App\Application\Mailing\Query\OutreachJournalPage;
use App\Application\Mailing\Query\OutreachJournalReaderInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineOutreachJournalReader implements OutreachJournalReaderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function getFilterChoices(): array
    {
        $organizations = [];
        foreach ($this->connection->fetchAllAssociative("SELECT HEX(snapshot.organization_uuid) AS uuid, MAX(snapshot.organization_name) AS name FROM mailing_delivery_recipient_organization snapshot INNER JOIN mailing_delivery_recipient recipient ON recipient.id = snapshot.delivery_recipient_id INNER JOIN mailing_campaign campaign ON campaign.uuid = UNHEX(REPLACE(recipient.campaign_uuid, '-', '')) WHERE campaign.campaign_type = 'outreach' GROUP BY snapshot.organization_uuid ORDER BY name") as $row) {
            $organizations[] = ['uuid' => Uuid::fromBinary((string) hex2bin((string) $row['uuid']))->toRfc4122(), 'name' => (string) $row['name']];
        }
        $campaigns = [];
        foreach ($this->connection->fetchAllAssociative("SELECT HEX(uuid) AS uuid, internal_title AS name FROM mailing_campaign WHERE campaign_type = 'outreach' ORDER BY internal_title") as $row) {
            $campaigns[] = ['uuid' => Uuid::fromBinary((string) hex2bin((string) $row['uuid']))->toRfc4122(), 'name' => (string) $row['name']];
        }
        $types = array_map(static fn (mixed $type): string => (string) $type, $this->connection->fetchFirstColumn("SELECT DISTINCT snapshot.organization_type FROM mailing_delivery_recipient_organization snapshot INNER JOIN mailing_delivery_recipient recipient ON recipient.id = snapshot.delivery_recipient_id INNER JOIN mailing_campaign campaign ON campaign.uuid = UNHEX(REPLACE(recipient.campaign_uuid, '-', '')) WHERE campaign.campaign_type = 'outreach' AND snapshot.organization_type IS NOT NULL ORDER BY snapshot.organization_type"));

        return ['organizations' => $organizations, 'campaigns' => $campaigns, 'types' => $types];
    }

    public function search(OutreachJournalFilter $filter): OutreachJournalPage
    {
        $query = $this->connection->createQueryBuilder()
            ->from('mailing_delivery_recipient_organization', 'snapshot')
            ->innerJoin('snapshot', 'mailing_delivery_recipient', 'recipient', 'recipient.id = snapshot.delivery_recipient_id')
            ->innerJoin('recipient', 'mailing_campaign', 'campaign', "campaign.uuid = UNHEX(REPLACE(recipient.campaign_uuid, '-', ''))")
            ->where('campaign.campaign_type = :type')->setParameter('type', 'outreach');
        $expr = $query->expr();
        if ($filter->organizationUuid) {
            $query->andWhere($expr->eq('HEX(snapshot.organization_uuid)', ':organization'))->setParameter('organization', strtoupper(bin2hex(Uuid::fromString($filter->organizationUuid)->toBinary())));
        }
        if ($filter->campaignUuid) {
            $query->andWhere($expr->eq('recipient.campaign_uuid', ':campaign'))->setParameter('campaign', $filter->campaignUuid);
        }
        if ($filter->organizationType) {
            $query->andWhere($expr->eq('snapshot.organization_type', ':organizationType'))->setParameter('organizationType', $filter->organizationType);
        }
        if ($filter->since) {
            $query->andWhere($expr->gte('COALESCE(recipient.sent_at, recipient.queued_at)', ':since'))->setParameter('since', $filter->since . ' 00:00:00');
        }
        if ($filter->until) {
            $query->andWhere($expr->lt('COALESCE(recipient.sent_at, recipient.queued_at)', ':until'))->setParameter('until', (new DateTimeImmutable($filter->until))->modify('+1 day')->format('Y-m-d 00:00:00'));
        }
        $total = (int) (clone $query)->select('COUNT(*)')->executeQuery()->fetchOne();
        $groups = (clone $query)->select('recipient.campaign_uuid', 'campaign.internal_title', 'snapshot.organization_type', 'MIN(recipient.sent_at) AS first_sent_at', 'MAX(recipient.sent_at) AS last_sent_at', 'COUNT(DISTINCT snapshot.organization_uuid) AS organization_count', 'COUNT(DISTINCT recipient.email_address) AS address_count')
            ->andWhere("recipient.status = 'sent'")->groupBy('recipient.campaign_uuid', 'campaign.internal_title', 'snapshot.organization_type')
            ->orderBy('last_sent_at', 'DESC')->addOrderBy('recipient.campaign_uuid')->addOrderBy('snapshot.organization_type')->setMaxResults(50)->executeQuery()->fetchAllAssociative();
        $rows = $query->select('snapshot.id', 'HEX(snapshot.organization_uuid) AS organization_uuid_hex', 'snapshot.organization_name', 'snapshot.organization_type', 'snapshot.municipality_insee_code', 'recipient.email_address', 'recipient.status', 'recipient.sent_at', 'recipient.queued_at', 'recipient.cancellation_reason', 'recipient.campaign_uuid', 'campaign.internal_title')
            ->orderBy('COALESCE(recipient.sent_at, recipient.queued_at)', 'DESC')->addOrderBy('snapshot.id', 'DESC')
            ->setMaxResults(50)->setFirstResult((max(1, $filter->page) - 1) * 50)->executeQuery()->fetchAllAssociative();
        foreach ($rows as &$row) {
            $row['organization_uuid'] = Uuid::fromBinary((string) hex2bin((string) $row['organization_uuid_hex']))->toRfc4122();
        }

        return new OutreachJournalPage($total, $rows, $groups);
    }
}
