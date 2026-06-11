<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObject;

final readonly class DepartmentCode
{
    public function __construct(private string $value)
    {
        if (1 !== preg_match('/^(?:0[1-9]|[1-8][0-9]|9[0-6]|2A|2B|97[1-8]|98[46789])$/', $value)) {
            throw new \InvalidArgumentException('Department code is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
