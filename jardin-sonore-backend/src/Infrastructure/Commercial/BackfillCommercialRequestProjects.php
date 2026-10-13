<?php

declare(strict_types=1);

namespace App\Infrastructure\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class BackfillCommercialRequestProjects
{
    private Connection $connection;

    public function __construct(EntityManagerInterface $entityManager, private ClockInterface $clock)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @param list<int>|null $requestIds */
    public function run(?array $requestIds = null): int
    {
        if ([] === $requestIds) {
            return 0;
        }
        $sql = 'SELECT request.id FROM commercial_request request WHERE NOT EXISTS (SELECT 1 FROM commercial_project project WHERE project.request_id = request.id)';
        if (null !== $requestIds) {
            $sql .= ' AND request.id IN (?)';
        }
        $sql .= ' ORDER BY request.id ASC';
        $pendingIds = $this->connection->fetchFirstColumn($sql, null === $requestIds ? [] : [$requestIds], null === $requestIds ? [] : [ArrayParameterType::INTEGER]);
        $created = 0;
        foreach ($pendingIds as $requestId) {
            $created += $this->connection->transactional(fn (): int => $this->createForRequest((int) $requestId));
        }

        return $created;
    }

    public function canonicalProjectId(int $requestId): ?int
    {
        $projectId = $this->connection->fetchOne(
            'SELECT id FROM commercial_project WHERE request_id = ? ORDER BY created_at ASC, id ASC LIMIT 1',
            [$requestId],
        );

        return false === $projectId ? null : (int) $projectId;
    }

    private function createForRequest(int $requestId): int
    {
        $request = $this->connection->fetchAssociative('SELECT * FROM commercial_request WHERE id = ? FOR UPDATE', [$requestId]);
        if (false === $request || null !== $this->canonicalProjectId($requestId)) {
            return 0;
        }

        $receivedAt = (string) $request['received_at'];
        $title = trim((string) ($request['organization_name'] ?: $request['sender_name']));
        if ('' === $title) {
            $title = 'Contact du ' . substr($receivedAt, 0, 10);
        }
        $isClosed = CommercialRequestEntity::STATUS_WITHOUT_RESULT === $request['status'];
        $organizationId = null === $request['organization_id'] ? null : (int) $request['organization_id'];
        $personId = null === $request['person_id'] ? null : (int) $request['person_id'];
        $this->connection->insert('commercial_project', [
            'title' => mb_substr($title, 0, 255),
            'status' => $isClosed ? CommercialProjectEntity::STATUS_WITHOUT_RESULT : CommercialProjectEntity::STATUS_DISCUSSION,
            'created_at' => $receivedAt,
            'closed_at' => null,
            'organization_id' => $organizationId,
            'request_id' => $requestId,
            'primary_contact_id' => $personId,
        ]);
        $projectId = (int) $this->connection->lastInsertId();
        if (null !== $personId) {
            $this->connection->insert('commercial_project_person', [
                'project_id' => $projectId,
                'person_id' => $personId,
                'linked_at' => $receivedAt,
                'current' => 1,
            ]);
        }
        if (CommercialRequestEntity::STATUS_TO_QUALIFY === $request['status']) {
            $today = $this->clock->now()->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d');
            $this->connection->insert('commercial_action', [
                'project_id' => $projectId,
                'title' => 'Vérifier la suite de ce contact',
                'due_on' => $today,
                'details' => null,
                'person_id' => $personId,
                'status' => 'open',
                'resolved_at' => null,
            ]);
        }

        return 1;
    }
}
