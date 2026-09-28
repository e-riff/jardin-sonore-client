<?php

declare(strict_types=1);

namespace App\Domain\Model\Portal;

final class PortalPasswordPolicy
{
    public const int MINIMUM_LENGTH = 12;

    public function isValid(string $password): bool
    {
        return self::MINIMUM_LENGTH <= mb_strlen($password)
            && 1 === preg_match('/[a-z]/', $password)
            && 1 === preg_match('/[A-Z]/', $password)
            && 1 === preg_match('/[0-9]/', $password);
    }
}
