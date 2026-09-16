<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final readonly class PortalSessionReader
{
    private const int PAGE_SIZE = 20;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return list<OrganizationEntity> */
    public function authorizedOrganizations(UserEntity $userEntity): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('organization')
            ->from(OrganizationEntity::class, 'organization')
            ->innerJoin(UserOrganizationAccessEntity::class, 'access', 'WITH', 'access.organization = organization')
            ->where('access.user = :user')
            ->andWhere('access.active = :active')
            ->setParameter('user', $userEntity)
            ->setParameter('active', true)
            ->orderBy('organization.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasActiveOrganizationAccess(UserEntity $userEntity): bool
    {
        return 0 < (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(access.id)')
            ->from(UserOrganizationAccessEntity::class, 'access')
            ->where('access.user = :user')
            ->andWhere('access.active = :active')
            ->setParameter('user', $userEntity)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array{items: list<SessionSummaryEntity>, total: int, page: int, pageSize: int} */
    public function paginated(UserEntity $userEntity, ?string $organizationUuid, int $page): array
    {
        $page = max(1, $page);
        $queryBuilder = $this->authorizedSessionsQueryBuilder($userEntity, $organizationUuid)
            ->orderBy('session.sessionDate', 'DESC')
            ->addOrderBy('session.updatedAt', 'DESC')
            ->addOrderBy('session.id', 'DESC');
        $total = (int) (clone $queryBuilder)
            ->select('COUNT(DISTINCT session.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $items = $queryBuilder
            ->select('DISTINCT session')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pageSize' => self::PAGE_SIZE];
    }

    public function findAuthorized(UserEntity $userEntity, string $sessionUuid): ?SessionSummaryEntity
    {
        if (!Uuid::isValid($sessionUuid)) {
            return null;
        }

        $sessionEntity = $this->authorizedSessionsQueryBuilder($userEntity)
            ->andWhere('session.uuid = :uuid')
            ->setParameter('uuid', Uuid::fromString($sessionUuid), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();

        return $sessionEntity instanceof SessionSummaryEntity ? $sessionEntity : null;
    }

    private function authorizedSessionsQueryBuilder(UserEntity $userEntity, ?string $organizationUuid = null): \Doctrine\ORM\QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('session')
            ->from(SessionSummaryEntity::class, 'session')
            ->innerJoin('session.organizations', 'organization')
            ->innerJoin(UserOrganizationAccessEntity::class, 'access', 'WITH', 'access.organization = organization')
            ->where('access.user = :user')
            ->andWhere('access.active = :active')
            ->setParameter('user', $userEntity)
            ->setParameter('active', true);

        if (null !== $organizationUuid && Uuid::isValid($organizationUuid)) {
            $queryBuilder
                ->andWhere('organization.uuid = :organizationUuid')
                ->setParameter('organizationUuid', Uuid::fromString($organizationUuid), UuidType::NAME);
        }

        return $queryBuilder;
    }
}
