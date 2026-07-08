<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Repository\AdminUserEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $entityManager,
        private AdminUserEntityRepository $adminUserEntityRepository,
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

        $adminUserEntity = $this->adminUserEntityRepository->findOneByEmailAddress($email);
        $created = false;

        if (!$adminUserEntity instanceof AdminUserEntity) {
            $adminUserEntity = new AdminUserEntity();
            $adminUserEntity->setEmail($email);
            $created = true;
        }

        $adminUserEntity
            ->setActive(true)
            ->setPassword($this->passwordHasher->hashPassword($adminUserEntity, $password));

        $this->entityManager->persist($adminUserEntity);
        $this->entityManager->flush();

        $io->success($created ? 'Admin user created.' : 'Admin user updated.');

        return Command::SUCCESS;
    }
}
