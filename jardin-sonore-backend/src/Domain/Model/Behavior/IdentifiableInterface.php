<?php

declare(strict_types=1);

namespace App\Domain\Model\Behavior;

interface IdentifiableInterface
{
    public function getId(): ?int;
}
