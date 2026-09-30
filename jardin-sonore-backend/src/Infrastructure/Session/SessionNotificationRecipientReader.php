<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Application\Session\SessionNotificationMailView;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\SessionNotificationDeliveryEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryOrganizationEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<UserEntity> */
final class SessionNotificationRecipientReader extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, UserEntity::class);
    }

    /** @return list<UserEntity> */
    public function eligibleUsers(SessionSummaryEntity $sessionSummaryEntity): array
    {
        $queryBuilder = $this->createQueryBuilder('portalUser');
        $expr = $queryBuilder->expr();

        return $queryBuilder
            ->distinct()
            ->innerJoin('portalUser.organizationAccesses', 'access')
            ->innerJoin(SessionSummaryOrganizationEntity::class, 'share', 'WITH', $expr->andX(
                $expr->eq('share.organization', 'access.organization'),
                $expr->eq('share.sessionSummary', ':session'),
            ))
            ->where($expr->andX(
                $expr->eq('portalUser.active', ':active'),
                $expr->eq('portalUser.status', ':status'),
                $expr->eq('portalUser.newSessionNotificationsEnabled', ':enabled'),
                $expr->eq('access.active', ':active'),
            ))
            ->setParameter('session', $sessionSummaryEntity)
            ->setParameter('active', true)
            ->setParameter('enabled', true)
            ->setParameter('status', UserStatus::ACTIVE->value)
            ->orderBy('portalUser.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function mailViewFor(SessionNotificationDeliveryEntity $sessionNotificationDeliveryEntity): ?SessionNotificationMailView
    {
        $queryBuilder = $this->createQueryBuilder('portalUser');
        $expr = $queryBuilder->expr();
        // Scalar hydration reads current account data and rights even in a long-lived worker.
        /** @var list<array{email: string, firstName: ?string, sessionTitle: string, sessionDate: DateTimeImmutable, sessionSlug: string, organizationName: string}> $rows */
        $rows = $queryBuilder
            ->select('portalUser.email AS email', 'portalUser.firstName AS firstName', 'summary.title AS sessionTitle', 'summary.sessionDate AS sessionDate', 'summary.slug AS sessionSlug', 'organization.name AS organizationName')
            ->distinct()
            ->innerJoin('portalUser.organizationAccesses', 'access')
            ->innerJoin(SessionSummaryOrganizationEntity::class, 'share', 'WITH', $expr->eq('share.organization', 'access.organization'))
            ->innerJoin('share.sessionSummary', 'summary')
            ->innerJoin('share.organization', 'organization')
            ->where($expr->andX(
                $expr->eq('portalUser', ':user'),
                $expr->eq('summary', ':session'),
                $expr->eq('summary.published', ':enabled'),
                $expr->eq('portalUser.active', ':enabled'),
                $expr->eq('portalUser.status', ':status'),
                $expr->eq('portalUser.newSessionNotificationsEnabled', ':enabled'),
                $expr->eq('access.active', ':enabled'),
            ))
            ->setParameter('user', $sessionNotificationDeliveryEntity->getUser())
            ->setParameter('session', $sessionNotificationDeliveryEntity->getSessionSummary())
            ->setParameter('enabled', true)
            ->setParameter('status', UserStatus::ACTIVE->value)
            ->orderBy('organization.name', 'ASC')
            ->getQuery()->getArrayResult();
        if ([] === $rows) {
            return null;
        }
        $first = $rows[0];

        return new SessionNotificationMailView(
            email: $first['email'],
            firstName: $first['firstName'],
            sessionTitle: $first['sessionTitle'],
            sessionDate: $first['sessionDate'],
            sessionSlug: $first['sessionSlug'],
            organizationNames: array_values(array_unique(array_column($rows, 'organizationName'))),
        );
    }
}
