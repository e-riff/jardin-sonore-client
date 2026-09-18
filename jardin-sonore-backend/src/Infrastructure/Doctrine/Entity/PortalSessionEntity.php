<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class PortalSessionEntity
{
    use IdentifiableTrait;

    private ?DateTimeImmutable $revokedAt = null;

    public function __construct(
        private UserEntity $user,
        private string $tokenHash,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $expiresAt,
        private ?AdminUserEntity $impersonatedBy = null,
    ) {
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getImpersonatedBy(): ?AdminUserEntity
    {
        return $this->impersonatedBy;
    }

    public function isUsableAt(DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && $this->expiresAt > $now;
    }

    public function revokeAt(DateTimeImmutable $now): void
    {
        if ($this->isUsableAt($now)) {
            $this->revokedAt = $now;
        }
    }
}
