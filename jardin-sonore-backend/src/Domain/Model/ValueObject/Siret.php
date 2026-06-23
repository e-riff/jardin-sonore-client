<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObject;

use InvalidArgumentException;

final readonly class Siret
{
    public function __construct(private string $value)
    {
        if (1 !== preg_match('/^\d{14}$/', $value)) {
            throw new InvalidArgumentException('SIRET must contain exactly 14 digits.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
