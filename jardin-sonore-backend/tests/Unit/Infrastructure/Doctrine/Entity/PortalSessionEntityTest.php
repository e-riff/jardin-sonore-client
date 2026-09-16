<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\PortalSessionEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PortalSessionEntityTest extends TestCase
{
    public function testAnActiveSessionIsUsableBeforeItsExpiry(): void
    {
        $now = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $portalSessionEntity = new PortalSessionEntity(
            new UserEntity(),
            hash('sha256', 'raw-portal-token'),
            $now,
            $now->modify('+7 days'),
        );

        self::assertTrue($portalSessionEntity->isUsableAt($now));
    }

    public function testARevokedSessionIsNotUsable(): void
    {
        $now = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $portalSessionEntity = new PortalSessionEntity(
            new UserEntity(),
            hash('sha256', 'raw-portal-token'),
            $now,
            $now->modify('+7 days'),
        );

        $portalSessionEntity->revokeAt($now);

        self::assertFalse($portalSessionEntity->isUsableAt($now));
        self::assertSame($now, $portalSessionEntity->getRevokedAt());
    }

    public function testASessionIsNotUsableAtItsExpiry(): void
    {
        $now = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $portalSessionEntity = new PortalSessionEntity(
            new UserEntity(),
            hash('sha256', 'raw-portal-token'),
            $now->modify('-7 days'),
            $now,
        );

        self::assertFalse($portalSessionEntity->isUsableAt($now));
    }
}
