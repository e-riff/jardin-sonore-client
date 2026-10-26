<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use Doctrine\DBAL\Connection;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'GeographicalReferenceTable', template: 'components/GeographicalReferenceTable.html.twig')]
final class GeographicalReferenceTable
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $kind = 'regions';

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public string $sortBy = '';

    #[LiveProp(writable: true)]
    public string $sortDirection = 'asc';

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array{id: int, code: string, name: string, parent: ?string}> */
    public function getRows(): array
    {
        $isDepartment = 'departments' === $this->kind;
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('item.id', 'item.code', 'item.name', $isDepartment ? 'region.name AS parent_name' : 'NULL AS parent_name')
            ->from($isDepartment ? 'department' : 'region', 'item');
        if ($isDepartment) {
            $queryBuilder->innerJoin('item', 'region', 'region', 'region.id = item.region_id');
        }
        if ('' !== trim($this->query)) {
            $queryBuilder->where('LOWER(item.name) LIKE LOWER(:query)')
                ->setParameter('query', '%' . addcslashes(trim($this->query), '%_\\') . '%');
            if ($isDepartment) {
                $queryBuilder->orWhere('LOWER(item.code) LIKE LOWER(:query)');
            }
        }
        $sortColumns = $isDepartment
            ? ['code' => 'item.code', 'name' => 'item.name', 'region' => 'region.name']
            : ['code' => 'item.code', 'name' => 'item.name'];
        $sortColumn = $sortColumns[$this->getEffectiveSortBy()];
        $sortDirection = 'desc' === strtolower($this->sortDirection) ? 'DESC' : 'ASC';
        $queryBuilder->orderBy($sortColumn, $sortDirection)->addOrderBy('item.id', 'ASC');

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'parent' => is_string($row['parent_name']) ? $row['parent_name'] : null,
        ], $queryBuilder->executeQuery()->fetchAllAssociative());
    }

    public function getEffectiveSortBy(): string
    {
        $allowedColumns = 'departments' === $this->kind ? ['code', 'name', 'region'] : ['code', 'name'];
        if (in_array($this->sortBy, $allowedColumns, true)) {
            return $this->sortBy;
        }

        return 'departments' === $this->kind ? 'code' : 'name';
    }

    public function isSortedBy(string $column): bool
    {
        return $this->getEffectiveSortBy() === $column;
    }

    public function sortIconFor(string $column): string
    {
        if (!$this->isSortedBy($column)) {
            return '↕';
        }

        return 'desc' === strtolower($this->sortDirection) ? '↓' : '↑';
    }

    #[LiveAction]
    public function sort(#[LiveArg] string $column): void
    {
        $allowedColumns = 'departments' === $this->kind ? ['code', 'name', 'region'] : ['code', 'name'];
        if (!in_array($column, $allowedColumns, true)) {
            return;
        }

        if ($this->getEffectiveSortBy() === $column) {
            $this->sortDirection = 'asc' === strtolower($this->sortDirection) ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }
}
