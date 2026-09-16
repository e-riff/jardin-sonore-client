<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Entity;

use App\Domain\Model\Portal\PasswordTokenType;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserPasswordTokenEntity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserPasswordTokenEntityTest extends TestCase
{
    public function testAUsableTokenCannotBeConsumedTwice(): void
    {
        $now = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $userPasswordTokenEntity = new UserPasswordTokenEntity(
            new UserEntity(),
            PasswordTokenType::INVITATION,
            hash('sha256', 'opaque-token'),
            $now->modify('+1 hour'),
        );

        self::assertTrue($userPasswordTokenEntity->isUsableAt($now));

        $userPasswordTokenEntity->consumeAt($now);

        self::assertFalse($userPasswordTokenEntity->isUsableAt($now));
    }

    public function testAnExpiredOrInvalidatedTokenIsNotUsable(): void
    {
        $now = new DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $expiredUserPasswordTokenEntity = new UserPasswordTokenEntity(
            new UserEntity(),
            PasswordTokenType::PASSWORD_RESET,
            hash('sha256', 'expired-token'),
            $now,
        );
        $invalidatedUserPasswordTokenEntity = new UserPasswordTokenEntity(
            new UserEntity(),
            PasswordTokenType::PASSWORD_RESET,
            hash('sha256', 'invalidated-token'),
            $now->modify('+1 hour'),
        );
        $invalidatedUserPasswordTokenEntity->invalidateAt($now);

        self::assertFalse($expiredUserPasswordTokenEntity->isUsableAt($now));
        self::assertFalse($invalidatedUserPasswordTokenEntity->isUsableAt($now));
    }
}
