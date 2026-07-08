<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AdminUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof AdminUserEntity) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('security.login.disabled_account');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
