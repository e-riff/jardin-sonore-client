<?php

declare(strict_types=1);

namespace App\Application\Security;

interface AdminPasswordHasherInterface
{
    public function hashPassword(string $email, string $plainPassword): string;
}
