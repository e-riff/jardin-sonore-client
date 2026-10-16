<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use DateTimeImmutable;

class GoogleCalendarConnectionEntity
{
    public const int SINGLETON_ID = 1;

    private int $id = self::SINGLETON_ID;

    private ?DateTimeImmutable $lastSuccessAt = null;

    public function __construct(
        private string $encryptedRefreshToken,
        private string $accountEmail,
        private DateTimeImmutable $connectedAt,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEncryptedRefreshToken(): string
    {
        return $this->encryptedRefreshToken;
    }

    public function getAccountEmail(): string
    {
        return $this->accountEmail;
    }

    public function getConnectedAt(): DateTimeImmutable
    {
        return $this->connectedAt;
    }

    public function getLastSuccessAt(): ?DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function reconnect(string $encryptedRefreshToken, string $accountEmail, DateTimeImmutable $connectedAt): void
    {
        $this->encryptedRefreshToken = $encryptedRefreshToken;
        $this->accountEmail = $accountEmail;
        $this->connectedAt = $connectedAt;
        $this->lastSuccessAt = null;
    }

    public function markAccessSucceeded(DateTimeImmutable $now): void
    {
        $this->lastSuccessAt = $now;
    }
}
