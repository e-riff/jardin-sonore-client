<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use Doctrine\DBAL\Connection;

final readonly class MailingReportQuery
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return array{total: int, rows: list<array<string, mixed>>} */
    public function search(string $campaignUuid, string $kind, string $search, int $page, int $limit = 25): array
    {
        $params = ['campaign' => $campaignUuid, 'search' => '%' . addcslashes(trim($search), '%_\\') . '%'];
        if ('organizations' === $kind) {
            $source = "SELECT DISTINCT organization.id, organization.name FROM mailing_delivery_recipient delivery
                INNER JOIN email_contact email ON CAST(LOWER(TRIM(email.email_address)) AS BINARY) = CAST(LOWER(TRIM(delivery.email_address)) AS BINARY)
                INNER JOIN contact_details_email_link link ON link.email_contact_id = email.id AND link.active = 1
                INNER JOIN contact_details details ON details.id = link.contact_details_id
                INNER JOIN directory_entry entry ON entry.id = details.directory_entry_id AND entry.active = 1
                LEFT JOIN person ON person.id = entry.id
                INNER JOIN organization ON organization.id = COALESCE(person.organization_id, entry.id)
                WHERE delivery.campaign_uuid = :campaign AND delivery.status = 'sent' AND organization.name LIKE :search
                UNION
                SELECT DISTINCT organization.id, organization.name FROM mailing_delivery_recipient delivery
                INNER JOIN portal_user ON CAST(LOWER(TRIM(portal_user.email)) AS BINARY) = CAST(LOWER(TRIM(delivery.email_address)) AS BINARY)
                INNER JOIN user_organization_access access_link ON access_link.user_id = portal_user.id AND access_link.active = 1
                INNER JOIN organization ON organization.id = access_link.organization_id
                INNER JOIN directory_entry entry ON entry.id = organization.id AND entry.active = 1
                WHERE delivery.campaign_uuid = :campaign AND delivery.status = 'sent' AND organization.name LIKE :search";
            $order = 'name, id';
        } else {
            $source = 'SELECT delivery.email_address, delivery.display_name, delivery.status, delivery.sent_at, delivery.last_error FROM mailing_delivery_recipient delivery WHERE delivery.campaign_uuid = :campaign AND delivery.email_address LIKE :search';
            $order = 'email_address';
        }
        $total = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM ({$source}) report_rows", $params);
        $offset = (max(1, $page) - 1) * $limit;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM ({$source}) report_rows ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}", $params);

        return ['total' => $total, 'rows' => $rows];
    }
}
