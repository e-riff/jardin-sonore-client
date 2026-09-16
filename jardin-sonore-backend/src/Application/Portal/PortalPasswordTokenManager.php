<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Domain\Model\Portal\PasswordTokenType;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserPasswordTokenEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PortalPasswordTokenManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        #[Autowire('%app.portal.password_link_ttl%')]
        private readonly int $passwordLinkTtl,
    ) {
    }

    public function issueInvitation(UserEntity $userEntity): IssuedPortalPasswordToken
    {
        if (UserStatus::PENDING !== $userEntity->getStatus()) {
            throw new LogicException('Only pending portal accounts can receive invitations.');
        }

        return $this->issue($userEntity, PasswordTokenType::INVITATION);
    }

    public function issuePasswordReset(UserEntity $userEntity): IssuedPortalPasswordToken
    {
        if (UserStatus::ACTIVE !== $userEntity->getStatus()) {
            throw new LogicException('Only active portal accounts can reset their password.');
        }

        return $this->issue($userEntity, PasswordTokenType::PASSWORD_RESET);
    }

    public function findUsable(string $rawToken): ?UserPasswordTokenEntity
    {
        $userPasswordTokenEntity = $this->entityManager->getRepository(UserPasswordTokenEntity::class)
            ->findOneBy(['tokenHash' => hash('sha256', $rawToken)]);

        return $userPasswordTokenEntity instanceof UserPasswordTokenEntity && $userPasswordTokenEntity->isUsableAt($this->clock->now())
            ? $userPasswordTokenEntity
            : null;
    }

    public function consumeWithPassword(UserPasswordTokenEntity $userPasswordTokenEntity, string $plainPassword): void
    {
        $now = $this->clock->now();

        if (!$userPasswordTokenEntity->isUsableAt($now)) {
            throw new LogicException('The portal password token is no longer usable.');
        }

        $userEntity = $userPasswordTokenEntity->getUser();
        $userEntity->setPassword($this->userPasswordHasher->hashPassword($userEntity, $plainPassword));
        $userPasswordTokenEntity->consumeAt($now);

        if (PasswordTokenType::INVITATION === $userPasswordTokenEntity->getType()) {
            $userEntity->setStatus(UserStatus::ACTIVE);
        }

        $this->entityManager->flush();
    }

    private function issue(UserEntity $userEntity, PasswordTokenType $passwordTokenType): IssuedPortalPasswordToken
    {
        $now = $this->clock->now();

        foreach ($userEntity->getPasswordTokens() as $userPasswordTokenEntity) {
            if ($passwordTokenType === $userPasswordTokenEntity->getType()) {
                $userPasswordTokenEntity->invalidateAt($now);
            }
        }

        $rawToken = bin2hex(random_bytes(32));
        $userPasswordTokenEntity = new UserPasswordTokenEntity(
            $userEntity,
            $passwordTokenType,
            hash('sha256', $rawToken),
            $now->modify("+{$this->passwordLinkTtl} seconds"),
        );
        $userEntity->addPasswordToken($userPasswordTokenEntity);
        $this->entityManager->persist($userPasswordTokenEntity);
        $this->entityManager->flush();

        return new IssuedPortalPasswordToken($userPasswordTokenEntity, $rawToken);
    }
}
