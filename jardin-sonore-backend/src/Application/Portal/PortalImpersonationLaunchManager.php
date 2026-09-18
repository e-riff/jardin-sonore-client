<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\PortalImpersonationLaunchEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PortalImpersonationLaunchManager
{
    private const int MAX_LAUNCH_TTL = 300;
    private const int MAX_IMPERSONATION_SESSION_TTL = 1800;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly PortalSessionManager $portalSessionManager,
        #[Autowire(service: 'monolog.logger.portal_security')]
        private readonly LoggerInterface $portalSecurityLogger,
        #[Autowire('%app.portal.impersonation_launch_ttl%')]
        private readonly int $launchTtl,
        #[Autowire('%app.portal.impersonation_session_ttl%')]
        private readonly int $impersonationSessionTtl,
    ) {
        if (0 >= $this->launchTtl || self::MAX_LAUNCH_TTL < $this->launchTtl) {
            throw new InvalidArgumentException('The portal impersonation launch TTL must be between 1 and 300 seconds.');
        }

        if (0 >= $this->impersonationSessionTtl || self::MAX_IMPERSONATION_SESSION_TTL < $this->impersonationSessionTtl) {
            throw new InvalidArgumentException('The portal impersonation session TTL must be between 1 and 1800 seconds.');
        }
    }

    public function issue(UserEntity $userEntity, AdminUserEntity $adminUserEntity): IssuedPortalImpersonationLaunch
    {
        if (UserStatus::ACTIVE !== $userEntity->getStatus()) {
            throw new LogicException('Only active portal accounts can be impersonated.');
        }

        $now = $this->clock->now();
        $rawToken = bin2hex(random_bytes(32));
        $portalImpersonationLaunchEntity = new PortalImpersonationLaunchEntity(
            $userEntity,
            $adminUserEntity,
            hash('sha256', $rawToken),
            $now,
            $now->modify("+{$this->launchTtl} seconds"),
        );
        $this->entityManager->persist($portalImpersonationLaunchEntity);
        $this->entityManager->flush();
        $this->portalSecurityLogger->info('Portal impersonation launch issued.', $this->auditContext($adminUserEntity, $userEntity));

        return new IssuedPortalImpersonationLaunch($portalImpersonationLaunchEntity, $rawToken);
    }

    public function consume(string $rawLaunchToken): IssuedPortalSession
    {
        /** @var array{issuedPortalSession: IssuedPortalSession, issuedBy: AdminUserEntity, user: UserEntity}|null $consumption */
        $consumption = $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($rawLaunchToken): ?array {
            $portalImpersonationLaunchEntity = $entityManager->getRepository(PortalImpersonationLaunchEntity::class)
                ->findOneBy(['tokenHash' => hash('sha256', $rawLaunchToken)]);
            if (!$portalImpersonationLaunchEntity instanceof PortalImpersonationLaunchEntity) {
                return null;
            }

            $entityManager->refresh($portalImpersonationLaunchEntity, LockMode::PESSIMISTIC_WRITE);
            $now = $this->clock->now();
            if (!$portalImpersonationLaunchEntity->isUsableAt($now)) {
                return null;
            }

            $userEntity = $portalImpersonationLaunchEntity->getUser();
            if (UserStatus::ACTIVE !== $userEntity->getStatus()) {
                $portalImpersonationLaunchEntity->invalidateAt($now);
                $entityManager->flush();

                return null;
            }

            $portalImpersonationLaunchEntity->consumeAt($now);
            $issuedPortalSession = $this->portalSessionManager->create(
                $userEntity,
                $portalImpersonationLaunchEntity->getIssuedBy(),
                $now->modify("+{$this->impersonationSessionTtl} seconds"),
            );

            return [
                'issuedPortalSession' => $issuedPortalSession,
                'issuedBy' => $portalImpersonationLaunchEntity->getIssuedBy(),
                'user' => $userEntity,
            ];
        });

        if (null === $consumption) {
            throw new LogicException('The portal impersonation launch is no longer usable.');
        }

        $this->portalSecurityLogger->info('Portal impersonation launch consumed.', $this->auditContext(
            $consumption['issuedBy'],
            $consumption['user'],
        ));

        return $consumption['issuedPortalSession'];
    }

    public function invalidateForUser(UserEntity $userEntity): void
    {
        $now = $this->clock->now();
        $portalImpersonationLaunchEntities = $this->entityManager->getRepository(PortalImpersonationLaunchEntity::class)
            ->findBy(['user' => $userEntity, 'consumedAt' => null, 'invalidatedAt' => null]);

        foreach ($portalImpersonationLaunchEntities as $portalImpersonationLaunchEntity) {
            if ($portalImpersonationLaunchEntity instanceof PortalImpersonationLaunchEntity) {
                $portalImpersonationLaunchEntity->invalidateAt($now);
            }
        }

        $this->entityManager->flush();
    }

    /** @return array{issuer: string, target: string} */
    private function auditContext(AdminUserEntity $adminUserEntity, UserEntity $userEntity): array
    {
        return [
            'issuer' => $adminUserEntity->getEmail(),
            'target' => $userEntity->getEmail(),
        ];
    }
}
