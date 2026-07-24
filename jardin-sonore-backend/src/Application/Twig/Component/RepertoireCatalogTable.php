<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Application\Session\RepertoireItemView;
use App\Application\Session\SearchRepertoireItems;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Repository\ThemeRepositoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name: 'RepertoireCatalogTable',
    template: 'components/RepertoireCatalogTable.html.twig',
    method: 'get',
)]
final class RepertoireCatalogTable
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true, onUpdated: 'refreshResults')]
    public string $query = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'refreshResults')]
    public string $type = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'refreshResults')]
    public string $activeFilter = 'active';
    /** @var list<string> */
    #[LiveProp(writable: true, url: true, onUpdated: 'refreshResults')]
    public array $themeUuids = [];

    #[LiveProp(url: true)]
    public string $sessionQuery = '';

    #[LiveProp(writable: true, url: true)]
    public string $sortBy = 'updatedAt';

    #[LiveProp(writable: true, url: true)]
    public string $sortDirection = 'desc';

    /**
     * @var list<RepertoireItemView>|null
     */
    private ?array $items = null;

    public function __construct(private readonly SearchRepertoireItems $searchRepertoireItems, private readonly ThemeRepositoryInterface $themeRepository)
    {
    }

    /**
     * @return list<RepertoireItemView>
     */
    public function getItems(): array
    {
        if (is_array($this->items)) {
            return $this->items;
        }

        $items = ($this->searchRepertoireItems)(
            $this->resolveRepertoireItemType(),
            trim($this->query),
        );
        if ([] !== $this->themeUuids) {
            $items = array_values(array_filter($items, fn (RepertoireItemView $item): bool => [] !== array_intersect($this->themeUuids, array_column($item->themes, 'uuid'))));
        }
        if ('all' !== $this->activeFilter) {
            $items = array_values(array_filter($items, fn (RepertoireItemView $item): bool => 'active' === $this->activeFilter ? $item->active : !$item->active));
        }

        usort($items, function (RepertoireItemView $left, RepertoireItemView $right): int {
            $leftValue = match ($this->sortBy) {
                'type' => $left->type->value,
                'title' => $left->title,
                'theme' => $left->themes[0]['label'] ?? '',
                default => $left->updatedAt->format('U.u'),
            };
            $rightValue = match ($this->sortBy) {
                'type' => $right->type->value,
                'title' => $right->title,
                'theme' => $right->themes[0]['label'] ?? '',
                default => $right->updatedAt->format('U.u'),
            };
            $comparison = strnatcasecmp($leftValue, $rightValue);

            if (0 === $comparison) {
                $comparison = $left->uuid->toRfc4122() <=> $right->uuid->toRfc4122();
            }

            return 'desc' === $this->sortDirection ? -$comparison : $comparison;
        });

        $this->items = $items;

        return $this->items;
    }

    /**
     * @return list<RepertoireItemType>
     */
    public function getTypeOptions(): array
    {
        return RepertoireItemType::cases();
    }

    /** @return list<array{uuid:string,label:string,color:string}> */
    public function getThemeOptions(): array
    {
        return array_map(static fn ($theme): array => ['uuid' => $theme->getUuid()->toRfc4122(), 'label' => $theme->getLabel(), 'color' => $theme->getColor()], $this->themeRepository->findAllOrderedByLabel());
    }

    #[LiveAction]
    public function toggleTheme(#[LiveArg] string $uuid): void
    {
        $this->themeUuids = in_array($uuid, $this->themeUuids, true)
            ? array_values(array_filter($this->themeUuids, static fn (string $themeUuid): bool => $themeUuid !== $uuid))
            : [...$this->themeUuids, $uuid];
        $this->refreshResults();
    }

    #[LiveAction]
    public function resetFilters(): void
    {
        $this->query = '';
        $this->type = '';
        $this->activeFilter = 'active';
        $this->themeUuids = [];
        $this->items = null;
    }

    #[LiveAction]
    public function sort(#[LiveArg] string $column): void
    {
        if (!in_array($column, ['type', 'title', 'theme'], true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = 'asc' === $this->sortDirection ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->items = null;
    }

    public function hasActiveFilters(): bool
    {
        return '' !== trim($this->query) || '' !== $this->type || 'active' !== $this->activeFilter || [] !== $this->themeUuids;
    }

    public function refreshResults(): void
    {
        $this->items = null;
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

    private function resolveRepertoireItemType(): ?RepertoireItemType
    {
        foreach (RepertoireItemType::cases() as $typeOption) {
            if ($typeOption->value === $this->type) {
                return $typeOption;
            }
        }

        return null;
    }
}
