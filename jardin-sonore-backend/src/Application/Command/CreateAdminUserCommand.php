<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Model\Administration\AdminUser;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\AdminUserRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create-admin',
    description: 'Create or update the unique backend administrator.',
)]
final readonly class CreateAdminUserCommand
{
    public function __construct(
        private AdminUserRepositoryInterface $adminUserRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Admin email address.')]
        string $email,
        #[Option(description: 'Admin plain password. If omitted, the command asks for it interactively.')]
        ?string $password = null,
    ): int {
        $email = mb_strtolower(trim($email));

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('The admin email address is invalid.');

            return Command::FAILURE;
        }

        $password ??= $io->askHidden('Admin password');

        if (!is_string($password) || 12 > mb_strlen($password)) {
            $io->error('The admin password must contain at least 12 characters.');

            return Command::FAILURE;
        }

        $emailAddress = new EmailAddress($email);
        $passwordHash = $this->hashPassword($email, $password);
        $adminUser = $this->adminUserRepository->findByEmailAddress($emailAddress);
        $created = false;

        if (!$adminUser instanceof AdminUser) {
            $adminUser = new AdminUser($emailAddress, $passwordHash);
            $created = true;
        } else {
            $adminUser->changeEmailAddress($emailAddress);
            $adminUser->changePasswordHash($passwordHash);
            $adminUser->activate();
        }

        $this->adminUserRepository->save($adminUser);

        $io->success($created ? 'Admin user created.' : 'Admin user updated.');

        return Command::SUCCESS;
    }

    private function hashPassword(string $email, string $password): string
    {
        $adminUserEntity = (new AdminUserEntity())->setEmail($email);

        return $this->passwordHasher->hashPassword($adminUserEntity, $password);
    }
}
