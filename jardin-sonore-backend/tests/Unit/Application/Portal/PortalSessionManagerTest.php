<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Portal;

use App\Application\Portal\PortalSessionManager;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\PortalSessionEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class PortalSessionManagerTest extends TestCase
{
    public function testItPersistsOnlyTheHashOfANewSevenDaySessionToken(): void
    {
        $now = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $userEntity = (new UserEntity())->setStatus(UserStatus::ACTIVE);
        $persistedPortalSessionEntity = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persistedPortalSessionEntity): void {
                $persistedPortalSessionEntity = $entity;
            },
        );
        $entityManager->expects(self::once())->method('flush');
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock($now), 604800);

        $issuedPortalSession = $portalSessionManager->create($userEntity);

        self::assertInstanceOf(PortalSessionEntity::class, $persistedPortalSessionEntity);
        self::assertSame(hash('sha256', $issuedPortalSession->rawToken), $persistedPortalSessionEntity->getTokenHash());
        self::assertEquals($now->modify('+604800 seconds'), $persistedPortalSessionEntity->getExpiresAt());
    }

    public function testItRejectsAnInactiveAccountWhenAuthenticatingAToken(): void
    {
        $now = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $portalSessionEntity = new PortalSessionEntity(
            (new UserEntity())->setStatus(UserStatus::INACTIVE),
            hash('sha256', 'raw-token'),
            $now,
            $now->modify('+7 days'),
        );
        $portalSessionRepository = $this->createMock(EntityRepository::class);
        $portalSessionRepository->expects(self::once())->method('findOneBy')->with(['tokenHash' => hash('sha256', 'raw-token')])->willReturn($portalSessionEntity);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(PortalSessionEntity::class)->willReturn($portalSessionRepository);
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock($now), 604800);

        self::assertNull($portalSessionManager->findAuthenticatedUser('raw-token'));
    }

    public function testItRejectsAnActiveAccountWithoutAnActiveOrganizationAccessWhenAuthenticatingAToken(): void
    {
        $now = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $portalSessionEntity = new PortalSessionEntity(
            (new UserEntity())->setStatus(UserStatus::ACTIVE),
            hash('sha256', 'raw-token'),
            $now,
            $now->modify('+7 days'),
        );
        $portalSessionRepository = $this->createMock(EntityRepository::class);
        $portalSessionRepository->expects(self::once())->method('findOneBy')->with(['tokenHash' => hash('sha256', 'raw-token')])->willReturn($portalSessionEntity);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(PortalSessionEntity::class)->willReturn($portalSessionRepository);
        $portalSessionManager = new PortalSessionManager($entityManager, new MockClock($now), 604800);

        self::assertNull($portalSessionManager->findAuthenticatedUser('raw-token'));
    }
}
