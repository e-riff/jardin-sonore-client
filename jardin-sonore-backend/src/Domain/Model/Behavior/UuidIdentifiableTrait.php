<?php

declare(strict_types=1);

namespace App\Domain\Model\Behavior;

use Symfony\Component\Uid\Uuid;

trait UuidIdentifiableTrait
{
    private Uuid $uuid;

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    private function initializeUuid(?Uuid $uuid): void
    {
        $this->uuid = $uuid ?? Uuid::v7();
    }
}
