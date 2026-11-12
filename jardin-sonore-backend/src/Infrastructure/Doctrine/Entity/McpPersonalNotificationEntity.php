<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

final class McpPersonalNotificationEntity
{
    use IdentifiableTrait;

    private string $state = 'sending';

    private ?DateTimeImmutable $finishedAt = null;

    public function __construct(
        private string $requestId,
        private string $fingerprint,
        private DateTimeImmutable $createdAt,
    ) {
    }
}
