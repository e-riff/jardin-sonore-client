<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Listener;

use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Clock\ClockInterface;

#[AsDoctrineListener(event: Events::postUpdate, connection: 'default')]
final class PortalUserDisableInvalidator
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function postUpdate(PostUpdateEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $userEntity = $eventArgs->getObject();
        if (!$userEntity instanceof UserEntity) {
            return;
        }

        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($userEntity);
        $updatedStatus = $changeSet['status'][1] ?? null;
        if (UserStatus::INACTIVE !== $updatedStatus && UserStatus::INACTIVE->value !== $updatedStatus) {
            return;
        }

        $this->invalidateUserArtifacts($entityManager, $userEntity, $this->clock->now());
    }

    private function invalidateUserArtifacts(
        EntityManagerInterface $entityManager,
        UserEntity $userEntity,
        DateTimeImmutable $now,
    ): void {
        $userId = $userEntity->getId();
        if (null === $userId) {
            return;
        }

        $parameters = ['invalidatedAt' => $now, 'userId' => $userId, 'now' => $now];
        $types = ['invalidatedAt' => Types::DATETIME_IMMUTABLE, 'now' => Types::DATETIME_IMMUTABLE];
        $connection = $entityManager->getConnection();
        $connection->executeStatement(
            'UPDATE portal_session SET revoked_at = :invalidatedAt WHERE user_id = :userId AND revoked_at IS NULL AND expires_at > :now',
            $parameters,
            $types,
        );
        $connection->executeStatement(
            'UPDATE portal_impersonation_launch SET invalidated_at = :invalidatedAt WHERE user_id = :userId AND consumed_at IS NULL AND invalidated_at IS NULL AND expires_at > :now',
            $parameters,
            $types,
        );
    }
}
