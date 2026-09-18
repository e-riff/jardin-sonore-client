<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\PortalSessionEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PortalSessionManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        #[Autowire('%app.portal.session_ttl%')]
        private readonly int $sessionTtl,
    ) {
    }

    public function create(UserEntity $userEntity, ?AdminUserEntity $adminUserImpersonator = null, ?DateTimeImmutable $expiresAt = null): IssuedPortalSession
    {
        $now = $this->clock->now();
        $rawToken = bin2hex(random_bytes(32));
        $portalSessionEntity = new PortalSessionEntity(
            $userEntity,
            hash('sha256', $rawToken),
            $now,
            $expiresAt ?? $now->modify("+{$this->sessionTtl} seconds"),
            $adminUserImpersonator,
        );
        $this->entityManager->persist($portalSessionEntity);
        $this->entityManager->flush();

        return new IssuedPortalSession($portalSessionEntity, $rawToken);
    }

    public function findAuthenticatedUser(string $rawToken): ?UserEntity
    {
        $portalSessionEntity = $this->entityManager->getRepository(PortalSessionEntity::class)
            ->findOneBy(['tokenHash' => hash('sha256', $rawToken)]);

        if (!$portalSessionEntity instanceof PortalSessionEntity || !$portalSessionEntity->isUsableAt($this->clock->now())) {
            return null;
        }

        $userEntity = $portalSessionEntity->getUser();

        if (UserStatus::ACTIVE !== $userEntity->getStatus()) {
            return null;
        }

        foreach ($userEntity->getOrganizationAccesses() as $userOrganizationAccessEntity) {
            if ($userOrganizationAccessEntity->isActive()) {
                return $userEntity;
            }
        }

        return null;
    }

    public function revoke(string $rawToken): void
    {
        $portalSessionEntity = $this->entityManager->getRepository(PortalSessionEntity::class)
            ->findOneBy(['tokenHash' => hash('sha256', $rawToken)]);

        if (!$portalSessionEntity instanceof PortalSessionEntity) {
            return;
        }

        $portalSessionEntity->revokeAt($this->clock->now());
        $this->entityManager->flush();
    }

    public function revokeForUser(UserEntity $userEntity): void
    {
        $now = $this->clock->now();
        $portalSessionEntities = $this->entityManager->getRepository(PortalSessionEntity::class)
            ->findBy(['user' => $userEntity, 'revokedAt' => null]);

        foreach ($portalSessionEntities as $portalSessionEntity) {
            if ($portalSessionEntity instanceof PortalSessionEntity) {
                $portalSessionEntity->revokeAt($now);
            }
        }

        $this->entityManager->flush();
    }
}
