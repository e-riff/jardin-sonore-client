<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObject;

final readonly class PhoneNumber
{
    public function __construct(private string $value)
    {
        if (1 !== preg_match('/^\+?[0-9 .()-]{6,20}$/', $value)) {
            throw new \InvalidArgumentException('Phone number is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
