<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity\Behavior;

trait IdentifiableTrait
{
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
