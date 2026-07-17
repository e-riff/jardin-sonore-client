<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Application\Session\RepertoireItemView;
use App\Application\Session\SearchRepertoireItems;
use App\Domain\Model\Session\RepertoireItemType;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
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

    #[LiveProp(url: true)]
    public string $sessionQuery = '';

    /**
     * @var list<RepertoireItemView>|null
     */
    private ?array $items = null;

    public function __construct(private readonly SearchRepertoireItems $searchRepertoireItems)
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

        $this->items = ($this->searchRepertoireItems)(
            $this->resolveRepertoireItemType(),
            trim($this->query),
        );

        return $this->items;
    }

    /**
     * @return list<RepertoireItemType>
     */
    public function getTypeOptions(): array
    {
        return RepertoireItemType::cases();
    }

    #[LiveAction]
    public function resetFilters(): void
    {
        $this->query = '';
        $this->type = '';
        $this->items = null;
    }

    public function hasActiveFilters(): bool
    {
        return '' !== trim($this->query) || '' !== $this->type;
    }

    public function refreshResults(): void
    {
        $this->items = null;
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
