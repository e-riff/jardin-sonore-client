<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class CommercialContactDeliveryEntity
{
    use IdentifiableTrait;

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_SENT = 'sent';

    private string $status = self::STATUS_PENDING;
    private ?DateTimeImmutable $sentAt = null;
    private int $attempts = 0;
    private ?string $lastError = null;

    public function __construct(
        private CommercialRequestEntity $request,
        private string $payloadHash,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public function getRequest(): CommercialRequestEntity
    {
        return $this->request;
    }

    public function getPayloadHash(): string
    {
        return $this->payloadHash;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
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
