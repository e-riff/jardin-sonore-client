<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity\Behavior;

use Symfony\Component\Uid\Uuid;

trait UuidIdentifiableTrait
{
    private Uuid $uuid;

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function setUuid(Uuid $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    private function initializeUuid(): void
    {
        $this->uuid = Uuid::v7();
    }
}
