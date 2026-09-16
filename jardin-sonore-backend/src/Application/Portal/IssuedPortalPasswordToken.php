<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\UserPasswordTokenEntity;

final readonly class IssuedPortalPasswordToken
{
    public function __construct(public UserPasswordTokenEntity $tokenEntity, public string $rawToken)
    {
    }
}
