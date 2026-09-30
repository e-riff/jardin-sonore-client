<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Application\Session\Message\SendSessionNotificationMessage;
use App\Infrastructure\Doctrine\Entity\SessionNotificationDeliveryEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/** @extends ServiceEntityRepository<SessionNotificationDeliveryEntity> */
final class SessionNotificationDeliveryStore extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, SessionNotificationDeliveryEntity::class);
    }

    /** @param list<UserEntity> $userEntities */
    public function schedule(SessionSummaryEntity $sessionSummaryEntity, array $userEntities): void
    {
        foreach ($userEntities as $userEntity) {
            $this->getEntityManager()->persist(new SessionNotificationDeliveryEntity($sessionSummaryEntity, $userEntity));
        }
    }

    /** @return list<int> */
    public function queueableIds(int $recoverAfterMinutes = 15): array
    {
        $cutoff = $this->recoveryCutoff($recoverAfterMinutes);
        $queryBuilder = $this->createQueryBuilder('delivery');
        $expr = $queryBuilder->expr();
        $ids = $queryBuilder->select('delivery.id')
            ->where($expr->orX(
                $expr->eq('delivery.status', ':pending'),
                $expr->andX(
                    $expr->eq('delivery.status', ':queued'),
                    $expr->orX($expr->isNull('delivery.queuedAt'), $expr->lte('delivery.queuedAt', ':cutoff')),
                ),
            ))
            ->setParameter('pending', SessionNotificationDeliveryEntity::STATUS_PENDING)
            ->setParameter('queued', SessionNotificationDeliveryEntity::STATUS_QUEUED)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('delivery.id', 'ASC')
            ->setMaxResults(100)
            ->getQuery()->getSingleColumnResult();

        return array_map(intval(...), $ids);
    }

    public function dispatchPending(MessageBusInterface $messageBus, int $recoverAfterMinutes = 15): int
    {
        $cutoff = $this->recoveryCutoff($recoverAfterMinutes);
        $connection = $this->getEntityManager()->getConnection();
        $count = 0;
        foreach ($this->queueableIds($recoverAfterMinutes) as $deliveryId) {
            $queued = $connection->transactional(function () use ($deliveryId, $cutoff, $messageBus, $connection): bool {
                $sessionNotificationDeliveryEntity = $this->lockedDelivery($deliveryId);
                if (null === $sessionNotificationDeliveryEntity || !$this->isQueueable($sessionNotificationDeliveryEntity, $cutoff)) {
                    return false;
                }

                $connection->update('session_notification_delivery', [
                    'status' => SessionNotificationDeliveryEntity::STATUS_QUEUED,
                    'queued_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ], ['id' => $deliveryId]);
                $messageBus->dispatch(new SendSessionNotificationMessage($deliveryId));

                return true;
            });
            if ($queued) {
                ++$count;
            }
        }

        return $count;
    }

    /** @param callable(SessionNotificationDeliveryEntity): bool $send */
    public function deliver(int $deliveryId, callable $send): void
    {
        $connection = $this->getEntityManager()->getConnection();
        try {
            $connection->transactional(function () use ($deliveryId, $send, $connection): void {
                $sessionNotificationDeliveryEntity = $this->lockedDelivery($deliveryId);
                if (null === $sessionNotificationDeliveryEntity || $this->isTerminal($sessionNotificationDeliveryEntity)) {
                    return;
                }

                $sent = $send($sessionNotificationDeliveryEntity);
                $connection->update('session_notification_delivery', [
                    'status' => $sent ? SessionNotificationDeliveryEntity::STATUS_SENT : SessionNotificationDeliveryEntity::STATUS_SKIPPED,
                    'sent_at' => $sent ? (new DateTimeImmutable())->format('Y-m-d H:i:s') : null,
                    'attempts' => $sessionNotificationDeliveryEntity->getAttempts() + 1,
                    'last_error' => null,
                ], ['id' => $deliveryId]);
            });
        } catch (Throwable $throwable) {
            $connection->transactional(function () use ($deliveryId, $throwable, $connection): void {
                $sessionNotificationDeliveryEntity = $this->lockedDelivery($deliveryId);
                if (null === $sessionNotificationDeliveryEntity || $this->isTerminal($sessionNotificationDeliveryEntity)) {
                    return;
                }
                $connection->update('session_notification_delivery', [
                    'status' => SessionNotificationDeliveryEntity::STATUS_FAILED,
                    'attempts' => $sessionNotificationDeliveryEntity->getAttempts() + 1,
                    'last_error' => mb_substr($throwable->getMessage(), 0, 5000),
                ], ['id' => $deliveryId]);
            });

            throw $throwable;
        }
    }

    private function lockedDelivery(int $deliveryId): ?SessionNotificationDeliveryEntity
    {
        $queryBuilder = $this->createQueryBuilder('delivery');
        // Acquire the lock before any consistent read creates a REPEATABLE READ snapshot.
        $sessionNotificationDeliveryEntity = $queryBuilder
            ->where($queryBuilder->expr()->eq('delivery.id', ':deliveryId'))
            ->setParameter('deliveryId', $deliveryId)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $sessionNotificationDeliveryEntity instanceof SessionNotificationDeliveryEntity ? $sessionNotificationDeliveryEntity : null;
    }

    private function isTerminal(SessionNotificationDeliveryEntity $sessionNotificationDeliveryEntity): bool
    {
        return in_array($sessionNotificationDeliveryEntity->getStatus(), [SessionNotificationDeliveryEntity::STATUS_SENT, SessionNotificationDeliveryEntity::STATUS_SKIPPED], true);
    }

    private function isQueueable(SessionNotificationDeliveryEntity $sessionNotificationDeliveryEntity, DateTimeImmutable $cutoff): bool
    {
        return SessionNotificationDeliveryEntity::STATUS_PENDING === $sessionNotificationDeliveryEntity->getStatus()
            || (SessionNotificationDeliveryEntity::STATUS_QUEUED === $sessionNotificationDeliveryEntity->getStatus()
                && (null === $sessionNotificationDeliveryEntity->getQueuedAt() || $sessionNotificationDeliveryEntity->getQueuedAt() <= $cutoff));
    }

    private function recoveryCutoff(int $recoverAfterMinutes): DateTimeImmutable
    {
        if (1 > $recoverAfterMinutes || 525600 < $recoverAfterMinutes) {
            throw new InvalidArgumentException('Recovery delay must be between 1 and 525600 minutes.');
        }

        return new DateTimeImmutable("-{$recoverAfterMinutes} minutes");
    }
}
