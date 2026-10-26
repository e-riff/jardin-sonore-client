<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'PortalUserTable', template: 'components/PortalUserTable.html.twig')]
final class PortalUserTable
{
    use DefaultActionTrait;

    private const int PER_PAGE = 25;

    private const array SORTABLE_COLUMNS = ['email', 'firstName', 'lastName', 'organization', 'status', 'active', 'newSessionNotificationsEnabled'];

    #[LiveProp(writable: true, url: true, onUpdated: 'onFiltersUpdated')]
    public string $query = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'onFiltersUpdated')]
    public string $statusFilter = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'onFiltersUpdated')]
    public string $activeFilter = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'onFiltersUpdated')]
    public string $organizationFilter = '';

    #[LiveProp(writable: true, url: true)]
    public string $sortBy = 'email';

    #[LiveProp(writable: true, url: true)]
    public string $sortDirection = 'asc';

    #[LiveProp(writable: true, url: true)]
    public int $page = 1;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** @return list<UserEntity> */
    public function getUsers(): array
    {
        $queryBuilder = $this->entityManager->getRepository(UserEntity::class)
            ->createQueryBuilder('portalUser')
            ->leftJoin('portalUser.organizationAccesses', 'organizationAccess')->addSelect('organizationAccess')
            ->leftJoin('organizationAccess.organization', 'organization')->addSelect('organization')
            ->distinct();

        $this->applySearch($queryBuilder);

        if ('' !== $this->statusFilter && null !== UserStatus::tryFrom($this->statusFilter)) {
            $queryBuilder->andWhere('portalUser.status = :status')->setParameter('status', UserStatus::from($this->statusFilter));
        }

        if ('enabled' === $this->activeFilter || 'disabled' === $this->activeFilter) {
            $queryBuilder->andWhere('portalUser.active = :active')->setParameter('active', 'enabled' === $this->activeFilter);
        }

        $organizationSearch = trim($this->organizationFilter);
        if ('' !== $organizationSearch) {
            $queryBuilder->andWhere('LOWER(organization.name) LIKE LOWER(:organizationSearch)')
                ->setParameter('organizationSearch', '%' . addcslashes($organizationSearch, '%_\\') . '%');
        }

        $sortBy = in_array($this->sortBy, self::SORTABLE_COLUMNS, true) ? $this->sortBy : 'email';
        $sortField = 'organization' === $sortBy ? 'organization.name' : 'portalUser.' . $sortBy;
        $sortDirection = 'desc' === strtolower($this->sortDirection) ? 'DESC' : 'ASC';
        $queryBuilder->orderBy($sortField, $sortDirection)->addOrderBy('portalUser.id', 'ASC')
            ->setFirstResult((max(1, $this->page) - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($queryBuilder, fetchJoinCollection: true);
        $users = iterator_to_array($paginator->getIterator());
        $pageCount = $this->getPageCount();
        if ($this->page > $pageCount) {
            $this->page = $pageCount;

            return $this->getUsers();
        }

        return $users;
    }

    public function getTotal(): int
    {
        return count($this->createPaginator());
    }

    public function getPageCount(): int
    {
        return max(1, (int) ceil($this->getTotal() / self::PER_PAGE));
    }

    public function isSortedBy(string $column): bool
    {
        return $this->sortBy === $column;
    }

    public function sortIconFor(string $column): string
    {
        if (!$this->isSortedBy($column)) {
            return '↕';
        }

        return 'asc' === strtolower($this->sortDirection) ? '↑' : '↓';
    }

    #[LiveAction]
    public function sort(#[LiveArg] string $column): void
    {
        if (!in_array($column, self::SORTABLE_COLUMNS, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = 'asc' === strtolower($this->sortDirection) ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->page = 1;
    }

    #[LiveAction]
    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    #[LiveAction]
    public function nextPage(): void
    {
        $this->page = min($this->getPageCount(), $this->page + 1);
    }

    public function onFiltersUpdated(): void
    {
        $this->page = 1;
    }

    /** @return Paginator<UserEntity> */
    private function createPaginator(): Paginator
    {
        $queryBuilder = $this->entityManager->getRepository(UserEntity::class)
            ->createQueryBuilder('portalUser')
            ->leftJoin('portalUser.organizationAccesses', 'organizationAccess')
            ->leftJoin('organizationAccess.organization', 'organization');

        $this->applySearch($queryBuilder);
        if ('' !== $this->statusFilter && null !== UserStatus::tryFrom($this->statusFilter)) {
            $queryBuilder->andWhere('portalUser.status = :status')->setParameter('status', UserStatus::from($this->statusFilter));
        }
        if ('enabled' === $this->activeFilter || 'disabled' === $this->activeFilter) {
            $queryBuilder->andWhere('portalUser.active = :active')->setParameter('active', 'enabled' === $this->activeFilter);
        }
        $organizationSearch = trim($this->organizationFilter);
        if ('' !== $organizationSearch) {
            $queryBuilder->andWhere('LOWER(organization.name) LIKE LOWER(:organizationSearch)')
                ->setParameter('organizationSearch', '%' . addcslashes($organizationSearch, '%_\\') . '%');
        }

        return new Paginator($queryBuilder->select('DISTINCT portalUser'), fetchJoinCollection: true);
    }

    private function applySearch(QueryBuilder $queryBuilder): void
    {
        $searchTerms = preg_split('/\s+/', trim($this->query), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($searchTerms as $index => $searchTerm) {
            $parameter = 'query' . $index;
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                "LOWER(portalUser.email) LIKE LOWER(:{$parameter})",
                "LOWER(portalUser.firstName) LIKE LOWER(:{$parameter})",
                "LOWER(portalUser.lastName) LIKE LOWER(:{$parameter})",
                "LOWER(organization.name) LIKE LOWER(:{$parameter})",
            ))->setParameter($parameter, '%' . addcslashes($searchTerm, '%_\\') . '%');
        }
    }
}
