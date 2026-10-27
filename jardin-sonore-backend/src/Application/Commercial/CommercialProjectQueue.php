<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;
use DateTimeZone;
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
    public function forDate(DateTimeImmutable $localDate, bool $includeClosed = false, ?DateTimeImmutable $now = null): array
    {
        $nowUtc = ($now ?? $localDate)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $statuses = $includeClosed
            ? [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED, CommercialProjectEntity::STATUS_COMPLETED, CommercialProjectEntity::STATUS_WITHOUT_RESULT]
            : [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED];
        $rows = $this->connection->fetchAllAssociative(
            'SELECT project.id, project.title, project.status, project.created_at,
                project.organization_id, organization.name AS organization_name,
                (SELECT CONCAT_WS(\' — \',
                    municipality.postal_code,
                    municipality.name
                 )
                 FROM contact_details
                 INNER JOIN address_contact ON address_contact.contact_details_id = contact_details.id
                 LEFT JOIN municipality ON municipality.id = address_contact.municipality_id
                 WHERE contact_details.directory_entry_id = organization.id
                 ORDER BY address_contact.id ASC LIMIT 1) AS organization_location,
                request.organization_name AS requested_organization_name, request.sender_name,
                action.id AS action_id, action.title AS action_title, action.due_on, action.due_time,
                (SELECT appointment.title FROM commercial_appointment appointment
                 WHERE appointment.project_id = project.id AND appointment.status = ?
                   AND appointment.starts_at >= ? AND appointment.starts_at < ? AND appointment.ends_at > ?
                 ORDER BY appointment.starts_at ASC, appointment.id ASC LIMIT 1) AS today_appointment_title,
                (SELECT appointment.starts_at FROM commercial_appointment appointment
                 WHERE appointment.project_id = project.id AND appointment.status = ?
                   AND appointment.starts_at >= ? AND appointment.starts_at < ? AND appointment.ends_at > ?
                 ORDER BY appointment.starts_at ASC, appointment.id ASC LIMIT 1) AS today_appointment_starts_at,
                (SELECT MAX(event.occurred_at) FROM commercial_event event WHERE event.project_id = project.id) AS last_event_at,
                (SELECT COUNT(*) FROM commercial_action counted WHERE counted.project_id = project.id AND counted.status = ?) AS open_task_count
             FROM commercial_project project
             LEFT JOIN organization ON organization.id = project.organization_id
             LEFT JOIN commercial_request request ON request.id = project.request_id
             LEFT JOIN commercial_action action ON action.id = (
                 SELECT earliest.id FROM commercial_action earliest
                 WHERE earliest.project_id = project.id AND earliest.status = ?
                 ORDER BY earliest.due_on ASC, earliest.due_time IS NULL ASC, earliest.due_time ASC, earliest.id ASC LIMIT 1
             )
             WHERE project.status IN (?)
             ORDER BY project.created_at DESC, project.id DESC',
            [
                CommercialAppointmentEntity::STATUS_SCHEDULED,
                $todayStartUtc = (new DateTimeImmutable($localDate->format('Y-m-d'), new DateTimeZone('Europe/Paris')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                $tomorrowStartUtc = (new DateTimeImmutable($localDate->format('Y-m-d'), new DateTimeZone('Europe/Paris')))->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                $nowUtc,
                CommercialAppointmentEntity::STATUS_SCHEDULED,
                $todayStartUtc,
                $tomorrowStartUtc,
                $nowUtc,
                'open', 'open', $statuses,
            ],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ArrayParameterType::STRING],
        );

        $today = $localDate->format('Y-m-d');
        $newThreshold = $localDate->modify('-1 day')->format('Y-m-d');
        $projects = [];
        foreach ($rows as $row) {
            $createdAt = new DateTimeImmutable((string) $row['created_at']);
            $dueOn = null === $row['due_on'] ? null : new DateTimeImmutable((string) $row['due_on']);
            $appointmentStartsAt = null === $row['today_appointment_starts_at'] ? null : new DateTimeImmutable((string) $row['today_appointment_starts_at'], new DateTimeZone('UTC'));
            $appointmentTodayHasPriority = null !== $appointmentStartsAt && (null === $dueOn || $dueOn->format('Y-m-d') > $today);
            $status = (string) $row['status'];
            $bucket = match (true) {
                in_array($status, [CommercialProjectEntity::STATUS_COMPLETED, CommercialProjectEntity::STATUS_WITHOUT_RESULT], true) => 'closed',
                $appointmentTodayHasPriority => 'priority',
                CommercialProjectEntity::STATUS_CONFIRMED === $status => 'confirmed',
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
                $appointmentTodayHasPriority || null === $row['action_id'] ? null : (int) $row['action_id'],
                $appointmentTodayHasPriority ? (string) $row['today_appointment_title'] : (null === $row['action_title'] ? null : (string) $row['action_title']),
                $appointmentTodayHasPriority ? new DateTimeImmutable($today, new DateTimeZone('Europe/Paris')) : $dueOn,
                $appointmentTodayHasPriority ? $appointmentStartsAt->setTimezone(new DateTimeZone('Europe/Paris'))->format('H:i') : (null === $row['due_time'] ? null : (string) $row['due_time']),
                null === $row['last_event_at'] ? null : new DateTimeImmutable((string) $row['last_event_at']),
                max(0, (int) $row['open_task_count'] - (null === $row['action_id'] ? 0 : 1)),
                $bucket,
                $appointmentTodayHasPriority,
                $createdAt->format('Y-m-d') >= $newThreshold && $createdAt->format('Y-m-d') <= $today,
                $localDate,
            );
        }

        return $projects;
    }
}
