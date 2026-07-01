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
}
