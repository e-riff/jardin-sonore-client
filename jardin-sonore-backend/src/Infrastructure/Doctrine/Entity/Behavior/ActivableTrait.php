<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity\Behavior;

trait ActivableTrait
{
    private bool $active = true;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
