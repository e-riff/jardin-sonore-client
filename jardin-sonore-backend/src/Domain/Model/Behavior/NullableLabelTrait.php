<?php

declare(strict_types=1);

namespace App\Domain\Model\Behavior;

trait NullableLabelTrait
{
    private ?string $label;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    private function initializeLabel(?string $label): void
    {
        $label = null !== $label ? trim($label) : null;
        $this->label = '' !== $label ? $label : null;
    }
}
