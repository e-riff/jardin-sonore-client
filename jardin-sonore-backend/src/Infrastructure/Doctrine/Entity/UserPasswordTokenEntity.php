<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\Portal\PasswordTokenType;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class UserPasswordTokenEntity
{
    use IdentifiableTrait;

    private ?DateTimeImmutable $consumedAt = null;
    private ?DateTimeImmutable $invalidatedAt = null;

    public function __construct(
        private UserEntity $user,
        private PasswordTokenType $type,
        private string $tokenHash,
        private DateTimeImmutable $expiresAt,
    ) {
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function getType(): PasswordTokenType
    {
        return $this->type;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getConsumedAt(): ?DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function getInvalidatedAt(): ?DateTimeImmutable
    {
        return $this->invalidatedAt;
    }

    public function isUsableAt(DateTimeImmutable $now): bool
    {
        return null === $this->consumedAt && null === $this->invalidatedAt && $this->expiresAt > $now;
    }

    public function consumeAt(DateTimeImmutable $now): void
    {
        if ($this->isUsableAt($now)) {
            $this->consumedAt = $now;
        }
    }

    public function invalidateAt(DateTimeImmutable $now): void
    {
        if ($this->isUsableAt($now)) {
            $this->invalidatedAt = $now;
        }
    }
}
