<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class MailingDeliveryRecipientEntity
{
    use IdentifiableTrait;

    private string $campaignUuid = '';

    private string $emailAddress = '';

    private string $unsubscribeToken = '';

    private ?string $displayName = null;

    private string $status = '';

    // hydrated by Doctrine
    // @phpstan-ignore-next-line
    private DateTimeImmutable $queuedAt;

    private ?DateTimeImmutable $dispatchedAt = null;

    private ?DateTimeImmutable $sentAt = null;

    private ?DateTimeImmutable $failedAt = null;

    // hydrated by Doctrine
    // @phpstan-ignore-next-line
    private DateTimeImmutable $updatedAt;

    private ?string $lastError = null;

    public function __toString(): string
    {
        return $this->emailAddress;
    }

    public function getCampaignUuid(): string
    {
        return $this->campaignUuid;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function getUnsubscribeToken(): string
    {
        return $this->unsubscribeToken;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getQueuedAt(): DateTimeImmutable
    {
        return $this->queuedAt;
    }

    public function getDispatchedAt(): ?DateTimeImmutable
    {
        return $this->dispatchedAt;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getFailedAt(): ?DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
