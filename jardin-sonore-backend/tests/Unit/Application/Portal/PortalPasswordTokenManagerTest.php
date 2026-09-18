<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Portal;

use App\Application\Portal\PortalPasswordTokenManager;
use App\Domain\Model\Portal\PasswordTokenType;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PortalPasswordTokenManagerTest extends TestCase
{
    public function testIssuingAReplacementInvitationInvalidatesThePreviousOne(): void
    {
        $now = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $userEntity = new UserEntity();
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $portalPasswordTokenManager = new PortalPasswordTokenManager(
            $entityManager,
            new MockClock($now),
            $this->createStub(UserPasswordHasherInterface::class),
            3600,
        );

        $firstIssuedPortalPasswordToken = $portalPasswordTokenManager->issueInvitation($userEntity);
        $secondIssuedPortalPasswordToken = $portalPasswordTokenManager->issueInvitation($userEntity);

        self::assertFalse($firstIssuedPortalPasswordToken->tokenEntity->isUsableAt($now));
        self::assertTrue($secondIssuedPortalPasswordToken->tokenEntity->isUsableAt($now));
        self::assertNotSame($firstIssuedPortalPasswordToken->rawToken, $secondIssuedPortalPasswordToken->rawToken);
        self::assertSame(hash('sha256', $secondIssuedPortalPasswordToken->rawToken), $secondIssuedPortalPasswordToken->tokenEntity->getTokenHash());
    }

    public function testConsumingAnInvitationSetsThePasswordAndActivatesTheUser(): void
    {
        $now = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $userEntity = new UserEntity();
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $userPasswordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $userPasswordHasher->method('hashPassword')->willReturn('hashed-password');
        $portalPasswordTokenManager = new PortalPasswordTokenManager(
            $entityManager,
            new MockClock($now),
            $userPasswordHasher,
            3600,
        );
        $issuedPortalPasswordToken = $portalPasswordTokenManager->issueInvitation($userEntity);

        $portalPasswordTokenManager->consumeWithPassword($issuedPortalPasswordToken->tokenEntity, 'Passphrase très solide 2026');

        self::assertSame('hashed-password', $userEntity->getPassword());
        self::assertSame(UserStatus::ACTIVE, $userEntity->getStatus());
        self::assertFalse($issuedPortalPasswordToken->tokenEntity->isUsableAt($now));
    }

    public function testConsumingAPasswordResetKeepsTheAccountActive(): void
    {
        $now = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $userEntity = (new UserEntity())->setStatus(UserStatus::ACTIVE);
        $userPasswordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $userPasswordHasher->method('hashPassword')->willReturn('new-hashed-password');
        $portalPasswordTokenManager = new PortalPasswordTokenManager(
            $this->createStub(EntityManagerInterface::class),
            new MockClock($now),
            $userPasswordHasher,
            3600,
        );

        $issuedPortalPasswordToken = $portalPasswordTokenManager->issuePasswordReset($userEntity);
        $portalPasswordTokenManager->consumeWithPassword($issuedPortalPasswordToken->tokenEntity, 'Une phrase de passe solide');

        self::assertSame(PasswordTokenType::PASSWORD_RESET, $issuedPortalPasswordToken->tokenEntity->getType());
        self::assertSame(UserStatus::ACTIVE, $userEntity->getStatus());
        self::assertSame('new-hashed-password', $userEntity->getPassword());
    }

    public function testOnlyPendingAccountsCanReceiveInvitations(): void
    {
        $portalPasswordTokenManager = new PortalPasswordTokenManager(
            $this->createStub(EntityManagerInterface::class),
            new MockClock(new DateTimeImmutable()),
            $this->createStub(UserPasswordHasherInterface::class),
            3600,
        );

        $this->expectException(LogicException::class);

        $portalPasswordTokenManager->issueInvitation((new UserEntity())->setStatus(UserStatus::INACTIVE));
    }

    public function testOnlyActiveAccountsCanReceivePasswordResets(): void
    {
        $portalPasswordTokenManager = new PortalPasswordTokenManager(
            $this->createStub(EntityManagerInterface::class),
            new MockClock(new DateTimeImmutable()),
            $this->createStub(UserPasswordHasherInterface::class),
            3600,
        );

        $this->expectException(LogicException::class);

        $portalPasswordTokenManager->issuePasswordReset(new UserEntity());
    }
}
