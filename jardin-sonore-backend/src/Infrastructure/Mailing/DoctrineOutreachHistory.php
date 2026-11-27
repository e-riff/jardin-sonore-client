<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\OutreachHistoryInterface;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\MailingDeliveryRecipientStatus;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineOutreachHistory implements OutreachHistoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findRecentlyContactedOrganizationUuids(string $excludedCampaignUuid, DateTimeImmutable $since): array
    {
        $query = $this->connection->createQueryBuilder();
        $expr = $query->expr();
        $rows = $query->select('DISTINCT HEX(snapshot.organization_uuid)')
            ->from('mailing_delivery_recipient_organization', 'snapshot')
            ->innerJoin('snapshot', 'mailing_delivery_recipient', 'recipient', 'recipient.id = snapshot.delivery_recipient_id')
            ->innerJoin('recipient', 'mailing_campaign', 'campaign', "campaign.uuid = UNHEX(REPLACE(recipient.campaign_uuid, '-', ''))")
            ->where($expr->and(
                $expr->eq('campaign.campaign_type', ':type'),
                $expr->eq('recipient.status', ':status'),
                $expr->gt('recipient.sent_at', ':since'),
                $expr->neq('recipient.campaign_uuid', ':excludedCampaignUuid'),
            ))
            ->setParameter('type', MailingCampaignType::OUTREACH->value)
            ->setParameter('status', MailingDeliveryRecipientStatus::SENT->value)
            ->setParameter('since', $since->format('Y-m-d H:i:s'))
            ->setParameter('excludedCampaignUuid', $excludedCampaignUuid)
            ->executeQuery()->fetchFirstColumn();

        return array_map(static fn (mixed $hex): string => Uuid::fromBinary((string) hex2bin((string) $hex))->toRfc4122(), $rows);
    }
}
