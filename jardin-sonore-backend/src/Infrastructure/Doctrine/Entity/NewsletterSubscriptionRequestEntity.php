<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class NewsletterSubscriptionRequestEntity
{
    use IdentifiableTrait;

    private ?DateTimeImmutable $consumedAt = null;

    public function __construct(
        private EmailContactEntity $emailContact,
        private string $tokenHash,
        private DateTimeImmutable $requestedAt,
        private DateTimeImmutable $expiresAt,
        private string $origin = 'footer',
    ) {
    }

    public function getEmailContact(): EmailContactEntity
    {
        return $this->emailContact;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getRequestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function isConsumed(): bool
    {
        return null !== $this->consumedAt;
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function consumeAt(DateTimeImmutable $now): void
    {
        $this->consumedAt = $now;
    }

    public function replace(string $tokenHash, DateTimeImmutable $now): void
    {
        $this->tokenHash = $tokenHash;
        $this->requestedAt = $now;
        $this->expiresAt = $now->modify('+48 hours');
        $this->consumedAt = null;
        $this->origin = 'footer';
    }
}
