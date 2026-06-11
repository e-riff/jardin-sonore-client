<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObject;

final readonly class InseeCode
{
    public function __construct(private string $value)
    {
        if (1 !== preg_match('/^\d{5}$/', $value)) {
            throw new \InvalidArgumentException('INSEE code must contain exactly 5 digits.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
