<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Listener;

use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Listener\PortalUserDisableInvalidator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Clock\MockClock;

final class PortalUserDisableInvalidatorTest extends TestCase
{
    public function testItAtomicallyInvalidatesUsablePortalArtifactsForEveryOrmStatusTransitionToInactive(): void
    {
        $now = new DateTimeImmutable('2026-09-17T10:00:00+00:00');
        $userEntity = (new UserEntity())->setStatus(UserStatus::ACTIVE);
        $userIdProperty = new ReflectionProperty($userEntity, 'id');
        $userIdProperty->setValue($userEntity, 42);
        $userEntity->setStatus(UserStatus::INACTIVE);
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects(self::once())->method('getEntityChangeSet')->with($userEntity)->willReturn([
            'status' => [UserStatus::ACTIVE->value, UserStatus::INACTIVE->value],
        ]);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('executeStatement')->willReturnCallback(
            function (string $sql, array $parameters, array $types) use ($now): int {
                static $call = 0;
                ++$call;

                if (1 === $call) {
                    self::assertSame('UPDATE portal_session SET revoked_at = :invalidatedAt WHERE user_id = :userId AND revoked_at IS NULL AND expires_at > :now', $sql);
                } else {
                    self::assertSame('UPDATE portal_impersonation_launch SET invalidated_at = :invalidatedAt WHERE user_id = :userId AND consumed_at IS NULL AND invalidated_at IS NULL AND expires_at > :now', $sql);
                }

                self::assertEquals(['invalidatedAt' => $now, 'userId' => 42, 'now' => $now], $parameters);
                self::assertSame(['invalidatedAt' => Types::DATETIME_IMMUTABLE, 'now' => Types::DATETIME_IMMUTABLE], $types);

                return 1;
            },
        );
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);
        $portalUserDisableInvalidator = new PortalUserDisableInvalidator(new MockClock($now));

        $portalUserDisableInvalidator->postUpdate(new PostUpdateEventArgs($userEntity, $entityManager));
    }
}
