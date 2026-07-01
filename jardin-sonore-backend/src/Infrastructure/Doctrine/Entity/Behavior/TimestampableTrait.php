<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity\Behavior;

use DateTimeImmutable;

trait TimestampableTrait
{
    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    private function initializeTimestamps(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }
}
