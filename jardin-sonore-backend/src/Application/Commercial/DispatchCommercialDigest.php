<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialDigestDeliveryEntity;
use App\Infrastructure\Doctrine\Entity\CommercialDigestSettingsEntity;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Throwable;

final readonly class DispatchCommercialDigest
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private BuildCommercialDigest $builder,
        private CommercialDigestSenderInterface $sender,
    ) {
    }

    public function run(): bool
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone('Europe/Paris'));
        if (6 <= (int) $now->format('N')) {
            return false;
        }
        $settingsEntity = $this->entityManager->find(CommercialDigestSettingsEntity::class, CommercialDigestSettingsEntity::SINGLETON_ID);
        if (!$settingsEntity instanceof CommercialDigestSettingsEntity || !$settingsEntity->isEnabled() || $now->format('H:i') < $settingsEntity->getSendTime()) {
            return false;
        }

        $localDate = new DateTimeImmutable($now->format('Y-m-d'), new DateTimeZone('Europe/Paris'));
        $digest = $this->builder->forDate($localDate);
        if ($digest->isEmpty()) {
            return false;
        }

        $this->entityManager->getConnection()->executeStatement(
            'INSERT IGNORE INTO commercial_digest_delivery (local_date, created_at, status, attempts) VALUES (?, ?, ?, 0)',
            [$localDate, $this->clock->now(), CommercialDigestDeliveryEntity::STATUS_PENDING],
            [Types::DATE_IMMUTABLE, Types::DATETIME_IMMUTABLE, Types::STRING],
        );

        $deliveryEntity = $this->entityManager->getRepository(CommercialDigestDeliveryEntity::class)->findOneBy(['localDate' => $localDate]);
        if (!$deliveryEntity instanceof CommercialDigestDeliveryEntity) {
            throw new RuntimeException('Could not reserve the commercial digest.');
        }
        $failure = null;
        $sent = $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($deliveryEntity, $digest, &$failure): bool {
            $entityManager->refresh($deliveryEntity, LockMode::PESSIMISTIC_WRITE);
            if (CommercialDigestDeliveryEntity::STATUS_SENT === $deliveryEntity->getStatus()) {
                return false;
            }
            try {
                $this->sender->send($digest);
                $deliveryEntity->markSent($this->clock->now());

                return true;
            } catch (Throwable $exception) {
                $deliveryEntity->markFailed($exception->getMessage());
                $failure = $exception;

                return false;
            }
        });
        if ($failure instanceof Throwable) {
            throw $failure;
        }

        return $sent;
    }
}
