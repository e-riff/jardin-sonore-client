<?php

declare(strict_types=1);

namespace App\Domain\Model\Behavior;

trait ActivableTrait
{
    private bool $active;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function setActive(bool $active): void
    {
        if ($active) {
            $this->activate();

            return;
        }

        $this->deactivate();
    }

    private function initializeActive(bool $active): void
    {
        $this->active = $active;
    }
}
