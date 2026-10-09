<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class CommercialEventEntity
{
    use IdentifiableTrait;

    /** @param array<string, string> $metadata */
    public function __construct(
        private CommercialProjectEntity $project,
        private string $type,
        private DateTimeImmutable $occurredAt,
        private ?string $content = null,
        private array $metadata = [],
        private ?PersonEntity $person = null,
    ) {
    }

    public function getProject(): CommercialProjectEntity
    {
        return $this->project;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    /** @return array<string, string> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getPerson(): ?PersonEntity
    {
        return $this->person;
    }
}
