<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObject;

use InvalidArgumentException;

final readonly class PhoneNumber
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = self::normalize($value);

        if (1 !== preg_match('/^\+?[0-9]{6,32}$/', $this->value)) {
            throw new InvalidArgumentException('Phone number is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public static function normalize(string $phoneNumber): string
    {
        $phoneNumber = trim($phoneNumber);
        $phoneNumber = str_replace(['.', '-', '(', ')'], '', $phoneNumber);
        $phoneNumber = preg_replace('/\s+/', '', $phoneNumber) ?? '';

        if (str_starts_with($phoneNumber, '00')) {
            $phoneNumber = '+' . substr($phoneNumber, 2);
        }

        if (1 === preg_match('/^0\d{9}$/', $phoneNumber)) {
            return '+33' . substr($phoneNumber, 1);
        }

        return $phoneNumber;
    }
}
