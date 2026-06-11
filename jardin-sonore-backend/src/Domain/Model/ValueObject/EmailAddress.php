<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObject;

final readonly class EmailAddress
{
    public function __construct(private string $value)
    {
        if (false === filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email address is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
