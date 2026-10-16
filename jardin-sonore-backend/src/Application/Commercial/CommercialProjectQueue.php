<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CommercialProjectQueue
{
    private Connection $connection;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @return list<CommercialProjectRow> */
    public function forDate(DateTimeImmutable $localDate, bool $includeClosed = false): array
    {
        $statuses = $includeClosed
            ? [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED, CommercialProjectEntity::STATUS_COMPLETED, CommercialProjectEntity::STATUS_WITHOUT_RESULT]
            : [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED];
        $rows = $this->connection->fetchAllAssociative(
            'SELECT project.id, project.title, project.status, project.created_at,
                project.organization_id, organization.name AS organization_name,
                (SELECT CONCAT_WS(\' — \',
                    COALESCE(municipality.postal_code, address_contact.postal_code),
                    COALESCE(municipality.name, address_contact.city)
                 )
                 FROM contact_details
                 INNER JOIN address_contact ON address_contact.contact_details_id = contact_details.id
                 LEFT JOIN municipality ON municipality.id = address_contact.municipality_id
                 WHERE contact_details.directory_entry_id = organization.id
                 ORDER BY address_contact.id ASC LIMIT 1) AS organization_location,
                request.organization_name AS requested_organization_name, request.sender_name,
                action.id AS action_id, action.title AS action_title, action.due_on,
                (SELECT COUNT(*) FROM commercial_action counted WHERE counted.project_id = project.id AND counted.status = ?) AS open_task_count
             FROM commercial_project project
             LEFT JOIN organization ON organization.id = project.organization_id
             LEFT JOIN commercial_request request ON request.id = project.request_id
             LEFT JOIN commercial_action action ON action.id = (
                 SELECT earliest.id FROM commercial_action earliest
                 WHERE earliest.project_id = project.id AND earliest.status = ?
                 ORDER BY earliest.due_on ASC, earliest.id ASC LIMIT 1
             )
             WHERE project.status IN (?)
             ORDER BY project.created_at DESC, project.id DESC',
            ['open', 'open', $statuses],
            [ParameterType::STRING, ParameterType::STRING, ArrayParameterType::STRING],
        );

        $today = $localDate->format('Y-m-d');
        $newThreshold = $localDate->modify('-6 days')->format('Y-m-d');
        $projects = [];
        foreach ($rows as $row) {
            $createdAt = new DateTimeImmutable((string) $row['created_at']);
            $dueOn = null === $row['due_on'] ? null : new DateTimeImmutable((string) $row['due_on']);
            $status = (string) $row['status'];
            $bucket = match (true) {
                in_array($status, [CommercialProjectEntity::STATUS_COMPLETED, CommercialProjectEntity::STATUS_WITHOUT_RESULT], true) => 'closed',
                null === $dueOn => 'without_task',
                $dueOn->format('Y-m-d') <= $today => 'priority',
                default => 'upcoming',
            };
            $organizationLabel = trim((string) ($row['organization_name'] ?: $row['requested_organization_name'] ?: $row['sender_name'] ?: $row['title']));
            $organizationLocation = trim((string) ($row['organization_location'] ?? ''));
            $projects[] = new CommercialProjectRow(
                (int) $row['id'],
                (string) $row['title'],
                $organizationLabel,
                '' !== $organizationLocation ? $organizationLocation : null,
                null === $row['organization_id'],
                $status,
                $createdAt,
                null === $row['action_id'] ? null : (int) $row['action_id'],
                null === $row['action_title'] ? null : (string) $row['action_title'],
                $dueOn,
                max(0, (int) $row['open_task_count'] - (null === $row['action_id'] ? 0 : 1)),
                $bucket,
                $createdAt->format('Y-m-d') >= $newThreshold && $createdAt->format('Y-m-d') <= $today,
                $localDate,
            );
        }

        return $projects;
    }
}
