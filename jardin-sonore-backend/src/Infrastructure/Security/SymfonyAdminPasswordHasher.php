<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Security\AdminPasswordHasherInterface;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class SymfonyAdminPasswordHasher implements AdminPasswordHasherInterface
{
    public function __construct(private UserPasswordHasherInterface $userPasswordHasher)
    {
    }

    public function hashPassword(string $email, string $plainPassword): string
    {
        $adminUserEntity = (new AdminUserEntity())->setEmail($email);

        return $this->userPasswordHasher->hashPassword($adminUserEntity, $plainPassword);
    }
}
