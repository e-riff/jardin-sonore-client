<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObject;

use InvalidArgumentException;

final readonly class RegionCode
{
    public function __construct(private string $value)
    {
        if (1 !== preg_match('/^\d{2,3}$/', $value)) {
            throw new InvalidArgumentException('Region code must contain 2 or 3 digits.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
