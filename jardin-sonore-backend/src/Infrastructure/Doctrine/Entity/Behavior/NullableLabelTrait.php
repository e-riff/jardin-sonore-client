<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity\Behavior;

trait NullableLabelTrait
{
    private ?string $label = null;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $label = null !== $label ? trim($label) : null;
        $this->label = '' !== $label ? $label : null;

        return $this;
    }
}
