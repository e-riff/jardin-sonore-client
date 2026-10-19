<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

final class McpMailDraftEntity
{
    use IdentifiableTrait;

    private ?int $imapUid = null;

    private string $state = 'reserved';

    private DateTimeImmutable $updatedAt;

    public function __construct(
        private string $sourceKey,
        private string $draftMessageId,
        private string $folder,
        private DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }
}
