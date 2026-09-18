<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Portal;

use App\Application\Portal\PortalImpersonationLaunchManager;
use App\Application\Portal\PortalSessionManager;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\PortalImpersonationLaunchEntity;
use App\Infrastructure\Doctrine\Entity\PortalSessionEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

final class PortalImpersonationLaunchManagerTest extends TestCase
{
    public function testItPersistsOnlyTheHashOfAnImpersonationLaunchAndLogsItsAuditContext(): void
    {
        $now = new DateTimeImmutable('2026-09-17T10:00:00+00:00');
        $adminUserEntity = (new AdminUserEntity())->setEmail('admin@jardin-sonore.test');
        $userEntity = (new UserEntity())->setEmail('structure@jardin-sonore.test')->setStatus(UserStatus::ACTIVE);
        $persistedPortalImpersonationLaunchEntity = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persistedPortalImpersonationLaunchEntity): void {
                $persistedPortalImpersonationLaunchEntity = $entity;
            },
        );
        $entityManager->expects(self::once())->method('flush');
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock($now), 604800);
        $portalSecurityLogger = $this->createMock(LoggerInterface::class);
        $portalSecurityLogger->expects(self::once())->method('info')->with('Portal impersonation launch issued.', [
            'issuer' => 'admin@jardin-sonore.test',
            'target' => 'structure@jardin-sonore.test',
        ]);
        $portalImpersonationLaunchManager = new PortalImpersonationLaunchManager(
            $entityManager,
            new MockClock($now),
            $portalSessionManager,
            $portalSecurityLogger,
            300,
            1800,
        );

        $issuedPortalImpersonationLaunch = $portalImpersonationLaunchManager->issue($userEntity, $adminUserEntity);

        self::assertInstanceOf(PortalImpersonationLaunchEntity::class, $persistedPortalImpersonationLaunchEntity);
        self::assertSame(hash('sha256', $issuedPortalImpersonationLaunch->rawToken), $persistedPortalImpersonationLaunchEntity->getTokenHash());
        self::assertEquals($now->modify('+5 minutes'), $persistedPortalImpersonationLaunchEntity->getExpiresAt());
    }

    public function testItConsumesALaunchOnlyOnceAndCapsTheImpersonatedSessionAtThirtyMinutes(): void
    {
        $now = new DateTimeImmutable('2026-09-17T10:00:00+00:00');
        $adminUserEntity = (new AdminUserEntity())->setEmail('admin@jardin-sonore.test');
        $userEntity = (new UserEntity())->setEmail('structure@jardin-sonore.test')->setStatus(UserStatus::ACTIVE);
        $portalImpersonationLaunchEntity = new PortalImpersonationLaunchEntity(
            $userEntity,
            $adminUserEntity,
            hash('sha256', 'launch-token'),
            $now,
            $now->modify('+5 minutes'),
        );
        $portalImpersonationLaunchRepository = $this->createMock(EntityRepository::class);
        $portalImpersonationLaunchRepository->expects(self::exactly(2))->method('findOneBy')
            ->with(['tokenHash' => hash('sha256', 'launch-token')])
            ->willReturn($portalImpersonationLaunchEntity);
        $persistedPortalSessionEntity = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(static fn (callable $callback) => $callback($entityManager));
        $entityManager->method('getRepository')->willReturnCallback(
            static fn (string $className): EntityRepository => PortalImpersonationLaunchEntity::class === $className
                ? $portalImpersonationLaunchRepository
                : throw new LogicException("Unexpected repository {$className}."),
        );
        $entityManager->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persistedPortalSessionEntity): void {
                $persistedPortalSessionEntity = $entity;
            },
        );
        $entityManager->expects(self::once())->method('flush');
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock($now), 604800);
        $portalSecurityLogger = $this->createMock(LoggerInterface::class);
        $portalSecurityLogger->expects(self::once())->method('info')->with('Portal impersonation launch consumed.', [
            'issuer' => 'admin@jardin-sonore.test',
            'target' => 'structure@jardin-sonore.test',
        ]);
        $portalImpersonationLaunchManager = new PortalImpersonationLaunchManager(
            $entityManager,
            new MockClock($now),
            $portalSessionManager,
            $portalSecurityLogger,
            300,
            1800,
        );

        $portalImpersonationLaunchManager->consume('launch-token');

        self::assertInstanceOf(PortalSessionEntity::class, $persistedPortalSessionEntity);
        self::assertSame($adminUserEntity, $persistedPortalSessionEntity->getImpersonatedBy());
        self::assertEquals($now->modify('+30 minutes'), $persistedPortalSessionEntity->getExpiresAt());
        self::expectException(LogicException::class);
        $portalImpersonationLaunchManager->consume('launch-token');
    }

    public function testItRejectsAnExpiredLaunchAtTheFiveMinuteBoundary(): void
    {
        $now = new DateTimeImmutable('2026-09-17T10:05:00+00:00');
        $portalImpersonationLaunchEntity = new PortalImpersonationLaunchEntity(
            (new UserEntity())->setStatus(UserStatus::ACTIVE),
            new AdminUserEntity(),
            hash('sha256', 'expired-launch-token'),
            $now->modify('-5 minutes'),
            $now,
        );
        $portalImpersonationLaunchRepository = $this->createMock(EntityRepository::class);
        $portalImpersonationLaunchRepository->expects(self::once())->method('findOneBy')
            ->with(['tokenHash' => hash('sha256', 'expired-launch-token')])
            ->willReturn($portalImpersonationLaunchEntity);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(static fn (callable $callback) => $callback($entityManager));
        $entityManager->method('getRepository')->with(PortalImpersonationLaunchEntity::class)->willReturn($portalImpersonationLaunchRepository);
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock($now), 604800);
        $portalImpersonationLaunchManager = new PortalImpersonationLaunchManager(
            $entityManager,
            new MockClock($now),
            $portalSessionManager,
            $this->createStub(LoggerInterface::class),
            300,
            1800,
        );

        $this->expectException(LogicException::class);
        $portalImpersonationLaunchManager->consume('expired-launch-token');
    }

    public function testItDoesNotAuditAConsumptionWhenTheTransactionFailsAfterItsCallback(): void
    {
        $now = new DateTimeImmutable('2026-09-17T10:00:00+00:00');
        $portalImpersonationLaunchEntity = new PortalImpersonationLaunchEntity(
            (new UserEntity())->setStatus(UserStatus::ACTIVE),
            new AdminUserEntity(),
            hash('sha256', 'rollback-launch-token'),
            $now,
            $now->modify('+5 minutes'),
        );
        $portalImpersonationLaunchRepository = $this->createMock(EntityRepository::class);
        $portalImpersonationLaunchRepository->expects(self::once())->method('findOneBy')
            ->with(['tokenHash' => hash('sha256', 'rollback-launch-token')])
            ->willReturn($portalImpersonationLaunchEntity);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('wrapInTransaction')->willReturnCallback(
            static function (callable $callback) use ($entityManager): never {
                $callback($entityManager);

                throw new RuntimeException('Commit failed.');
            },
        );
        $entityManager->method('getRepository')->with(PortalImpersonationLaunchEntity::class)->willReturn($portalImpersonationLaunchRepository);
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock($now), 604800);
        $portalSecurityLogger = $this->createMock(LoggerInterface::class);
        $portalSecurityLogger->expects(self::never())->method('info');
        $portalImpersonationLaunchManager = new PortalImpersonationLaunchManager(
            $entityManager,
            new MockClock($now),
            $portalSessionManager,
            $portalSecurityLogger,
            300,
            1800,
        );

        $this->expectException(RuntimeException::class);
        $portalImpersonationLaunchManager->consume('rollback-launch-token');
    }

    public function testItInvalidatesUnconsumedLaunchesWhenThePortalAccountIsDisabled(): void
    {
        $now = new DateTimeImmutable('2026-09-17T10:00:00+00:00');
        $userEntity = (new UserEntity())->setStatus(UserStatus::INACTIVE);
        $portalImpersonationLaunchEntity = new PortalImpersonationLaunchEntity(
            $userEntity,
            new AdminUserEntity(),
            hash('sha256', 'launch-token'),
            $now,
            $now->modify('+5 minutes'),
        );
        $portalImpersonationLaunchRepository = $this->createMock(EntityRepository::class);
        $portalImpersonationLaunchRepository->expects(self::once())->method('findBy')
            ->with(['user' => $userEntity, 'consumedAt' => null, 'invalidatedAt' => null])
            ->willReturn([$portalImpersonationLaunchEntity]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(PortalImpersonationLaunchEntity::class)->willReturn($portalImpersonationLaunchRepository);
        $entityManager->expects(self::once())->method('flush');
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock($now), 604800);
        $portalImpersonationLaunchManager = new PortalImpersonationLaunchManager(
            $entityManager,
            new MockClock($now),
            $portalSessionManager,
            $this->createStub(LoggerInterface::class),
            300,
            1800,
        );

        $portalImpersonationLaunchManager->invalidateForUser($userEntity);

        self::assertEquals($now, $portalImpersonationLaunchEntity->getInvalidatedAt());
    }

    public function testItInvalidatesAndRejectsALaunchConsumedAfterItsPortalAccountIsDisabled(): void
    {
        $now = new DateTimeImmutable('2026-09-17T10:00:00+00:00');
        $portalImpersonationLaunchEntity = new PortalImpersonationLaunchEntity(
            (new UserEntity())->setStatus(UserStatus::INACTIVE),
            new AdminUserEntity(),
            hash('sha256', 'disabled-launch-token'),
            $now,
            $now->modify('+5 minutes'),
        );
        $portalImpersonationLaunchRepository = $this->createMock(EntityRepository::class);
        $portalImpersonationLaunchRepository->expects(self::once())->method('findOneBy')
            ->with(['tokenHash' => hash('sha256', 'disabled-launch-token')])
            ->willReturn($portalImpersonationLaunchEntity);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(static fn (callable $callback) => $callback($entityManager));
        $entityManager->method('getRepository')->with(PortalImpersonationLaunchEntity::class)->willReturn($portalImpersonationLaunchRepository);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock($now), 604800);
        $portalImpersonationLaunchManager = new PortalImpersonationLaunchManager(
            $entityManager,
            new MockClock($now),
            $portalSessionManager,
            $this->createStub(LoggerInterface::class),
            300,
            1800,
        );

        try {
            $portalImpersonationLaunchManager->consume('disabled-launch-token');
            self::fail('A launch for a disabled portal account must be rejected.');
        } catch (LogicException) {
        }

        self::assertEquals($now, $portalImpersonationLaunchEntity->getInvalidatedAt());
    }

    public function testItRefreshesTheLaunchUnderAPessimisticLockInsideATransactionBeforeConsumption(): void
    {
        $now = new DateTimeImmutable('2026-09-17T10:00:00+00:00');
        $portalImpersonationLaunchEntity = new PortalImpersonationLaunchEntity(
            (new UserEntity())->setStatus(UserStatus::ACTIVE),
            new AdminUserEntity(),
            hash('sha256', 'locked-launch-token'),
            $now,
            $now->modify('+5 minutes'),
        );
        $portalImpersonationLaunchRepository = $this->createMock(EntityRepository::class);
        $portalImpersonationLaunchRepository->expects(self::once())->method('findOneBy')
            ->with(['tokenHash' => hash('sha256', 'locked-launch-token')])
            ->willReturn($portalImpersonationLaunchEntity);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $callback) => $callback($entityManager),
        );
        $entityManager->method('getRepository')->with(PortalImpersonationLaunchEntity::class)->willReturn($portalImpersonationLaunchRepository);
        $entityManager->expects(self::once())->method('refresh')->with($portalImpersonationLaunchEntity, LockMode::PESSIMISTIC_WRITE);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock($now), 604800);
        $portalImpersonationLaunchManager = new PortalImpersonationLaunchManager(
            $entityManager,
            new MockClock($now),
            $portalSessionManager,
            $this->createStub(LoggerInterface::class),
            300,
            1800,
        );

        $portalImpersonationLaunchManager->consume('locked-launch-token');
    }

    public function testItRejectsConfiguredLaunchTtlAboveItsSecurityCap(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock(), 604800);

        $this->expectException(InvalidArgumentException::class);
        new PortalImpersonationLaunchManager(
            $entityManager,
            new MockClock(),
            $portalSessionManager,
            $this->createStub(LoggerInterface::class),
            301,
            1800,
        );
    }

    public function testItRejectsConfiguredImpersonationSessionTtlAboveItsSecurityCap(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock(), 604800);

        $this->expectException(InvalidArgumentException::class);
        new PortalImpersonationLaunchManager(
            $entityManager,
            new MockClock(),
            $portalSessionManager,
            $this->createStub(LoggerInterface::class),
            300,
            1801,
        );
    }
}
