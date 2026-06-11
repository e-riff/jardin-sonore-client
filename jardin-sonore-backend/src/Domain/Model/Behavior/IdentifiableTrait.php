<?php

declare(strict_types=1);

namespace App\Domain\Model\Behavior;

trait IdentifiableTrait
{
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    private function initializeId(?int $id): void
    {
        $this->id = $id;
    }
}
