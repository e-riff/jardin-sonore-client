<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\UserEntity;

interface PortalAccountMailSenderInterface
{
    public function sendInvitation(UserEntity $userEntity, string $rawToken): void;

    public function sendPasswordReset(UserEntity $userEntity, string $rawToken): void;
}
