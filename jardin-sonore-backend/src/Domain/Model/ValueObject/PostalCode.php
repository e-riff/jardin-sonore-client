<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObject;

use InvalidArgumentException;

final readonly class PostalCode
{
    public function __construct(private string $value)
    {
        if (1 !== preg_match('/^\d{5}$/', $value)) {
            throw new InvalidArgumentException('Postal code must contain exactly 5 digits.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
