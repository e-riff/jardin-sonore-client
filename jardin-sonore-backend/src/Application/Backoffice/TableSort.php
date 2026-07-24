<?php

declare(strict_types=1);

namespace App\Application\Backoffice;

final readonly class TableSort
{
    private function __construct(
        public string $column,
        public string $direction,
    ) {
    }

    /** @param list<string> $allowedColumns */
    public static function fromQuery(
        string $column,
        string $direction,
        array $allowedColumns,
        string $defaultColumn,
        string $defaultDirection,
    ): self {
        if (!in_array($column, $allowedColumns, true)) {
            $column = $defaultColumn;
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDirection;
        }

        return new self($column, $direction);
    }

    public function nextDirectionFor(string $column): string
    {
        return $column === $this->column && 'asc' === $this->direction ? 'desc' : 'asc';
    }

    public function compare(float|int|string $left, float|int|string $right): int
    {
        $comparison = is_string($left) && is_string($right)
            ? strnatcasecmp($left, $right)
            : $left <=> $right;

        return 'desc' === $this->direction ? -$comparison : $comparison;
    }
}
