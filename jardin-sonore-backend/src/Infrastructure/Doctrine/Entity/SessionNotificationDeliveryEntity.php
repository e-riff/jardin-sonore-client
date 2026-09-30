<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class SessionNotificationDeliveryEntity
{
    use IdentifiableTrait;

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_QUEUED = 'queued';
    public const string STATUS_SENT = 'sent';
    public const string STATUS_SKIPPED = 'skipped';
    public const string STATUS_FAILED = 'failed';

    private string $status = self::STATUS_PENDING;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $queuedAt = null;
    private ?DateTimeImmutable $sentAt = null;
    private int $attempts = 0;
    private ?string $lastError = null;

    public function __construct(
        private SessionSummaryEntity $sessionSummary,
        private UserEntity $user,
        /** @var list<int>|null */
        private ?array $organizationIds = null,
    ) {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getSessionSummary(): SessionSummaryEntity
    {
        return $this->sessionSummary;
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    /** @return list<int>|null */
    public function getOrganizationIds(): ?array
    {
        return $this->organizationIds;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getQueuedAt(): ?DateTimeImmutable
    {
        return $this->queuedAt;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
