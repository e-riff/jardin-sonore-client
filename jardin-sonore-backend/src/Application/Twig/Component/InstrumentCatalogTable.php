<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Application\ContentCatalog\FindInstrumentCatalogItems;
use App\Application\ContentCatalog\InstrumentCatalogCriteria;
use App\Application\ContentCatalog\InstrumentCatalogResult;
use App\Domain\Repository\InstrumentTagRepositoryInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name: 'InstrumentCatalogTable',
    template: 'components/InstrumentCatalogTable.html.twig',
    method: 'get',
)]
final class InstrumentCatalogTable
{
    use DefaultActionTrait;

    private const int PER_PAGE = 15;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    /**
     * @var list<string>
     */
    #[LiveProp(writable: true, url: true)]
    public array $tagUuids = [];

    #[LiveProp(writable: true, url: true)]
    public string $activeFilter = '';

    #[LiveProp(writable: true, url: true)]
    public string $quantityFilter = '';

    #[LiveProp(writable: true, url: true)]
    public string $tuningFilter = '';

    #[LiveProp(writable: true, url: true)]
    public string $sortBy = 'name';

    #[LiveProp(writable: true, url: true)]
    public string $sortDirection = 'asc';

    #[LiveProp(writable: true, url: true)]
    public int $page = 1;

    private ?InstrumentCatalogResult $catalogResult = null;

    public function __construct(
        private readonly FindInstrumentCatalogItems $findInstrumentCatalogItems,
        private readonly InstrumentTagRepositoryInterface $instrumentTagRepository,
    ) {
    }

    #[LiveAction]
    public function sort(#[LiveArg] string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = 'asc' === $this->sortDirection ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'updatedAt' === $column ? 'desc' : 'asc';
        }

        $this->page = 1;
        $this->catalogResult = null;
    }

    #[LiveAction]
    public function previousPage(): void
    {
        if (1 < $this->page) {
            --$this->page;
            $this->catalogResult = null;
        }
    }

    #[LiveAction]
    public function nextPage(): void
    {
        if ($this->page < $this->getPageCount()) {
            ++$this->page;
            $this->catalogResult = null;
        }
    }

    #[LiveAction]
    public function resetFilters(): void
    {
        $this->query = '';
        $this->tagUuids = [];
        $this->activeFilter = '';
        $this->quantityFilter = '';
        $this->tuningFilter = '';
        $this->sortBy = 'name';
        $this->sortDirection = 'asc';
        $this->page = 1;
        $this->catalogResult = null;
    }

    public function getCatalogResult(): InstrumentCatalogResult
    {
        if ($this->catalogResult instanceof InstrumentCatalogResult) {
            return $this->catalogResult;
        }

        $this->catalogResult = ($this->findInstrumentCatalogItems)($this->createCriteria());
        $pageCount = $this->getPageCountFromTotal($this->catalogResult->total);

        if ($this->page > $pageCount) {
            $this->page = $pageCount;
            $this->catalogResult = ($this->findInstrumentCatalogItems)($this->createCriteria());
        }

        return $this->catalogResult;
    }

    /**
     * @return list<array{uuid:string,label:string}>
     */
    public function getTagOptions(): array
    {
        return array_map(
            static fn ($instrumentTag): array => [
                'uuid' => $instrumentTag->getUuid()->toRfc4122(),
                'label' => $instrumentTag->getLabel(),
            ],
            $this->instrumentTagRepository->findAllOrderedByLabel(),
        );
    }

    public function getPageCount(): int
    {
        return $this->getPageCountFromTotal($this->getCatalogResult()->total);
    }

    public function hasActiveFilters(): bool
    {
        return '' !== trim($this->query)
            || [] !== array_values(array_filter(array_map('trim', $this->tagUuids)))
            || '' !== $this->activeFilter
            || '' !== $this->quantityFilter
            || '' !== $this->tuningFilter;
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

        return 'asc' === $this->sortDirection ? '↑' : '↓';
    }

    private function createCriteria(): InstrumentCatalogCriteria
    {
        return new InstrumentCatalogCriteria(
            query: trim($this->query),
            tagUuids: array_values(array_unique(array_filter(
                array_map('trim', $this->tagUuids),
                static fn (string $uuid): bool => Uuid::isValid($uuid),
            ))),
            activeFilter: $this->activeFilter,
            quantityFilter: $this->quantityFilter,
            tuningFilter: $this->tuningFilter,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            page: max(1, $this->page),
            perPage: self::PER_PAGE,
        );
    }

    private function getPageCountFromTotal(int $total): int
    {
        return max(1, (int) ceil($total / self::PER_PAGE));
    }
}
