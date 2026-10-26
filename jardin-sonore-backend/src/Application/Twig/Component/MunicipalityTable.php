<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'MunicipalityTable', template: 'components/MunicipalityTable.html.twig')]
final class MunicipalityTable
{
    use DefaultActionTrait;

    private const int PER_PAGE = 50;

    private const array SORT_COLUMNS = [
        'name' => 'municipality.name',
        'inseeCode' => 'municipality.insee_code',
        'postalCode' => 'municipality.postal_code',
        'department' => 'department.code',
    ];

    #[LiveProp(writable: true, url: true, onUpdated: 'onFiltersUpdated')]
    public string $query = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'onDepartmentSelectionUpdated')]
    public string $departmentSelection = '42';

    #[LiveProp(writable: true, url: true)]
    public string $sortBy = 'name';

    #[LiveProp(writable: true, url: true)]
    public string $sortDirection = 'asc';

    #[LiveProp(writable: true, url: true)]
    public int $page = 1;

    /** @var array{rows: list<array{id: int, name: string, inseeCode: ?string, postalCode: ?string, departmentCode: string, departmentName: string}>, total: int, pageCount: int}|null */
    private ?array $pageData = null;

    /** @var list<array{code: string, name: string, departments: list<array{code: string, name: string}>}>|null */
    private ?array $departmentTree = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array{rows: list<array{id: int, name: string, inseeCode: ?string, postalCode: ?string, departmentCode: string, departmentName: string}>, total: int, pageCount: int} */
    public function getPageData(): array
    {
        if (null !== $this->pageData) {
            return $this->pageData;
        }

        $countQueryBuilder = $this->createQueryBuilder()->select('COUNT(municipality.id)');
        $this->applyFilters($countQueryBuilder);
        $total = (int) $countQueryBuilder->executeQuery()->fetchOne();
        $pageCount = max(1, (int) ceil($total / self::PER_PAGE));
        $this->page = min(max(1, $this->page), $pageCount);

        $rowsQueryBuilder = $this->createQueryBuilder()
            ->select(
                'municipality.id',
                'municipality.name',
                'municipality.insee_code',
                'municipality.postal_code',
                'department.code AS department_code',
                'department.name AS department_name',
            );
        $this->applyFilters($rowsQueryBuilder);

        $sortColumn = self::SORT_COLUMNS[$this->sortBy] ?? self::SORT_COLUMNS['name'];
        $sortDirection = 'desc' === strtolower($this->sortDirection) ? 'DESC' : 'ASC';
        $rowsQueryBuilder
            ->orderBy($sortColumn, $sortDirection)
            ->addOrderBy('municipality.id', 'ASC')
            ->setFirstResult(($this->page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $rows = array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'inseeCode' => is_string($row['insee_code']) ? $row['insee_code'] : null,
                'postalCode' => is_string($row['postal_code']) ? $row['postal_code'] : null,
                'departmentCode' => (string) $row['department_code'],
                'departmentName' => (string) $row['department_name'],
            ],
            $rowsQueryBuilder->executeQuery()->fetchAllAssociative(),
        );

        return $this->pageData = ['rows' => $rows, 'total' => $total, 'pageCount' => $pageCount];
    }

    /** @return list<array{code: string, name: string, departments: list<array{code: string, name: string}>}> */
    public function getDepartmentTree(): array
    {
        if (null !== $this->departmentTree) {
            return $this->departmentTree;
        }

        $rows = $this->connection->createQueryBuilder()
            ->select(
                'region.code AS region_code',
                'region.name AS region_name',
                'department.code AS department_code',
                'department.name AS department_name',
            )
            ->from('department', 'department')
            ->innerJoin('department', 'region', 'region', 'region.id = department.region_id')
            ->orderBy('region.name', 'ASC')
            ->addOrderBy('department.code', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $regions = [];
        foreach ($rows as $row) {
            $regionCode = (string) $row['region_code'];
            $regions[$regionCode] ??= [
                'code' => $regionCode,
                'name' => (string) $row['region_name'],
                'departments' => [],
            ];
            $regions[$regionCode]['departments'][] = [
                'code' => (string) $row['department_code'],
                'name' => (string) $row['department_name'],
            ];
        }

        return $this->departmentTree = array_values($regions);
    }

    /** @return list<string> */
    public function getSelectedDepartmentCodes(): array
    {
        if ('*' === $this->departmentSelection || '' === $this->departmentSelection) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $this->departmentSelection)))));
    }

    /** @return list<string> */
    public function getSelectedDepartmentLabels(): array
    {
        $selectedCodes = $this->getSelectedDepartmentCodes();
        if ([] === $selectedCodes) {
            return [];
        }

        $labels = [];
        foreach ($this->getDepartmentTree() as $region) {
            foreach ($region['departments'] as $department) {
                if (in_array($department['code'], $selectedCodes, true)) {
                    $labels[] = $department['code'] . ' — ' . $department['name'];
                }
            }
        }

        return $labels;
    }

    public function regionSelectionState(string $regionCode): string
    {
        foreach ($this->getDepartmentTree() as $region) {
            if ($region['code'] !== $regionCode) {
                continue;
            }

            $selectedCount = count(array_intersect(
                array_column($region['departments'], 'code'),
                $this->getSelectedDepartmentCodes(),
            ));

            return match (true) {
                0 === $selectedCount => 'unchecked',
                $selectedCount === count($region['departments']) => 'checked',
                default => 'partial',
            };
        }

        return 'unchecked';
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
        if (!isset(self::SORT_COLUMNS[$column])) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = 'asc' === strtolower($this->sortDirection) ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->page = 1;
        $this->pageData = null;
    }

    #[LiveAction]
    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
        $this->pageData = null;
    }

    #[LiveAction]
    public function nextPage(): void
    {
        $this->page = min($this->getPageData()['pageCount'], $this->page + 1);
        $this->pageData = null;
    }

    #[LiveAction]
    public function showAllMunicipalities(): void
    {
        $this->departmentSelection = '*';
        $this->page = 1;
        $this->pageData = null;
    }

    public function onFiltersUpdated(): void
    {
        $this->page = 1;
        $this->pageData = null;
    }

    public function onDepartmentSelectionUpdated(): void
    {
        $selectedDepartmentCodes = $this->getSelectedDepartmentCodes();
        $this->departmentSelection = [] === $selectedDepartmentCodes ? '*' : implode(',', $selectedDepartmentCodes);
        $this->onFiltersUpdated();
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from('municipality', 'municipality')
            ->innerJoin('municipality', 'department', 'department', 'department.id = municipality.department_id');
    }

    private function applyFilters(QueryBuilder $queryBuilder): void
    {
        $selectedDepartmentCodes = $this->getSelectedDepartmentCodes();
        if ([] !== $selectedDepartmentCodes) {
            $queryBuilder
                ->andWhere('department.code IN (:departmentCodes)')
                ->setParameter('departmentCodes', $selectedDepartmentCodes, ArrayParameterType::STRING);
        }

        $searchTerms = preg_split('/\s+/', trim($this->query), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($searchTerms as $index => $searchTerm) {
            $parameterName = 'query' . $index;
            $escapedSearchTerm = '%' . addcslashes($searchTerm, '%_\\') . '%';
            $queryBuilder->andWhere($queryBuilder->expr()->or(
                'LOWER(municipality.name) LIKE LOWER(:' . $parameterName . ')',
                'municipality.insee_code LIKE :' . $parameterName,
                'municipality.postal_code LIKE :' . $parameterName,
                'LOWER(department.code) LIKE LOWER(:' . $parameterName . ')',
                'LOWER(department.name) LIKE LOWER(:' . $parameterName . ')',
            ))->setParameter($parameterName, $escapedSearchTerm);
        }
    }
}
