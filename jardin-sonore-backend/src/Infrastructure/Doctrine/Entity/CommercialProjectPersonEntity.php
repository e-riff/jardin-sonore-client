<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class CommercialProjectPersonEntity
{
    use IdentifiableTrait;

    private bool $current = true;

    public function __construct(
        private CommercialProjectEntity $project,
        private PersonEntity $person,
        private DateTimeImmutable $linkedAt,
    ) {
    }

    public function getProject(): CommercialProjectEntity
    {
        return $this->project;
    }

    public function getPerson(): PersonEntity
    {
        return $this->person;
    }

    public function getLinkedAt(): DateTimeImmutable
    {
        return $this->linkedAt;
    }

    public function isCurrent(): bool
    {
        return $this->current;
    }

    public function markFormer(): void
    {
        $this->current = false;
    }

    public function markCurrent(): void
    {
        $this->current = true;
    }
}
