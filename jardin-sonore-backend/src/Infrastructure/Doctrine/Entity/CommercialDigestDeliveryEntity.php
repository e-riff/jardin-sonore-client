<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class CommercialDigestDeliveryEntity
{
    use IdentifiableTrait;

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_SENT = 'sent';
    public const string STATUS_FAILED = 'failed';

    private string $status = self::STATUS_PENDING;

    private int $attempts = 0;

    private ?DateTimeImmutable $sentAt = null;

    private ?string $lastError = null;

    public function __construct(
        private DateTimeImmutable $localDate,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public function getLocalDate(): DateTimeImmutable
    {
        return $this->localDate;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function markSent(DateTimeImmutable $sentAt): void
    {
        ++$this->attempts;
        $this->status = self::STATUS_SENT;
        $this->sentAt = $sentAt;
        $this->lastError = null;
    }

    public function markFailed(string $message): void
    {
        ++$this->attempts;
        $this->status = self::STATUS_FAILED;
        $this->lastError = mb_substr($message, 0, 255);
    }
}
