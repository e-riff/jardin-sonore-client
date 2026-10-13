<?php

declare(strict_types=1);

namespace App\Infrastructure\Commercial;

use App\Application\Commercial\CommercialContactRequestInput;
use App\Infrastructure\Doctrine\Entity\CommercialContactDeliveryEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Throwable;

final readonly class ContactRequestDeliveryStore
{
    private Connection $connection;

    public function __construct(private EntityManagerInterface $entityManager, private ClockInterface $clock)
    {
        $this->connection = $entityManager->getConnection();
    }

    public function record(CommercialContactRequestInput $input): int
    {
        return $this->connection->transactional(function () use ($input): int {
            $now = $this->clock->now()->format('Y-m-d H:i:s');
            $this->connection->executeStatement(
                'INSERT INTO commercial_request (source, sender_name, email_address, message, received_at, organization_name, city, phone, submission_key, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
                [
                    'site', $input->name, $input->emailAddress, $input->message, $now,
                    '' === $input->organizationName ? null : $input->organizationName,
                    '' === $input->city ? null : $input->city,
                    '' === $input->phone ? null : $input->phone,
                    $input->submissionKey, CommercialRequestEntity::STATUS_QUALIFIED,
                ],
            );
            $requestId = (int) $this->connection->lastInsertId();
            $request = $this->connection->fetchAssociative('SELECT id, received_at FROM commercial_request WHERE id = ? FOR UPDATE', [$requestId]);
            if (false === $request) {
                throw new RuntimeException('Contact request is missing after insert.');
            }
            $knownHash = $this->connection->fetchOne('SELECT payload_hash FROM commercial_contact_delivery WHERE request_id = ?', [$requestId]);
            if (false !== $knownHash && !hash_equals((string) $knownHash, $input->fingerprint())) {
                throw new DomainException('The submission key was already used for different contact data.');
            }

            $projectId = $this->connection->fetchOne('SELECT id FROM commercial_project WHERE request_id = ? ORDER BY created_at ASC, id ASC LIMIT 1', [$requestId]);
            if (false === $projectId) {
                $title = mb_substr('' === $input->organizationName ? $input->name : $input->organizationName, 0, 255);
                $this->connection->insert('commercial_project', [
                    'title' => $title,
                    'status' => CommercialProjectEntity::STATUS_DISCUSSION,
                    'created_at' => $request['received_at'],
                    'closed_at' => null,
                    'organization_id' => null,
                    'request_id' => $requestId,
                    'primary_contact_id' => null,
                ]);
                $projectId = (int) $this->connection->lastInsertId();
                $dueOn = $this->clock->now()->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d');
                $this->connection->insert('commercial_action', [
                    'project_id' => $projectId,
                    'title' => 'Répondre',
                    'due_on' => $dueOn,
                    'details' => null,
                    'person_id' => null,
                    'status' => 'open',
                    'resolved_at' => null,
                ]);
            }
            $this->connection->executeStatement('UPDATE commercial_request SET status = ? WHERE id = ? AND status = ?', [CommercialRequestEntity::STATUS_QUALIFIED, $requestId, CommercialRequestEntity::STATUS_TO_QUALIFY]);
            if (false === $knownHash) {
                $this->connection->insert('commercial_contact_delivery', [
                    'request_id' => $requestId,
                    'payload_hash' => $input->fingerprint(),
                    'status' => CommercialContactDeliveryEntity::STATUS_PENDING,
                    'created_at' => $now,
                    'attempts' => 0,
                ]);
            }

            return $requestId;
        });
    }

    /** @return list<int> */
    public function queueableIds(int $limit = 100): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM commercial_contact_delivery WHERE status IN (?, ?) ORDER BY id ASC LIMIT ' . max(1, min(100, $limit)),
            [CommercialContactDeliveryEntity::STATUS_PENDING, CommercialContactDeliveryEntity::STATUS_FAILED],
        );

        return array_map(intval(...), $ids);
    }

    /** @param callable(CommercialRequestEntity): void $send */
    public function deliver(int $deliveryId, callable $send): void
    {
        try {
            $this->connection->transactional(function () use ($deliveryId, $send): void {
                $delivery = $this->connection->fetchAssociative('SELECT request_id, status, attempts FROM commercial_contact_delivery WHERE id = ? FOR UPDATE', [$deliveryId]);
                if (false === $delivery || CommercialContactDeliveryEntity::STATUS_SENT === $delivery['status']) {
                    return;
                }

                $requestEntity = $this->entityManager->find(CommercialRequestEntity::class, (int) $delivery['request_id']);
                if (null === $requestEntity) {
                    throw new RuntimeException('Contact request is missing.');
                }

                $send($requestEntity);
                $this->connection->update('commercial_contact_delivery', [
                    'status' => CommercialContactDeliveryEntity::STATUS_SENT,
                    'sent_at' => $this->clock->now()->format('Y-m-d H:i:s'),
                    'attempts' => (int) $delivery['attempts'] + 1,
                    'last_error' => null,
                ], ['id' => $deliveryId]);
            });
        } catch (Throwable $throwable) {
            $this->connection->transactional(function () use ($deliveryId, $throwable): void {
                $delivery = $this->connection->fetchAssociative('SELECT status, attempts FROM commercial_contact_delivery WHERE id = ? FOR UPDATE', [$deliveryId]);
                if (false === $delivery || CommercialContactDeliveryEntity::STATUS_SENT === $delivery['status']) {
                    return;
                }
                $this->connection->update('commercial_contact_delivery', [
                    'status' => CommercialContactDeliveryEntity::STATUS_FAILED,
                    'attempts' => (int) $delivery['attempts'] + 1,
                    'last_error' => mb_substr($throwable->getMessage(), 0, 5000),
                ], ['id' => $deliveryId]);
            });

            throw $throwable;
        }
    }
}
