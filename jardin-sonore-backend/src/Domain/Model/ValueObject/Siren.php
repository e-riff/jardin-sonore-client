<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObject;

use InvalidArgumentException;

final readonly class Siren
{
    public function __construct(private string $value)
    {
        if (1 !== preg_match('/^\d{9}$/', $value)) {
            throw new InvalidArgumentException('SIREN must contain exactly 9 digits.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
