<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Application\Session\MediaResourceView;
use App\Application\Session\SearchMediaResources;
use App\Domain\Model\Session\MediaResourceType;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name: 'MediaResourceCatalogTable',
    template: 'components/MediaResourceCatalogTable.html.twig',
    method: 'get',
)]
final class MediaResourceCatalogTable
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true, onUpdated: 'refreshResults')]
    public string $query = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'refreshResults')]
    public string $type = '';

    #[LiveProp(url: true)]
    public string $sessionQuery = '';

    /**
     * @var list<MediaResourceView>|null
     */
    private ?array $items = null;

    public function __construct(private readonly SearchMediaResources $searchMediaResources)
    {
    }

    /**
     * @return list<MediaResourceView>
     */
    public function getItems(): array
    {
        if (is_array($this->items)) {
            return $this->items;
        }

        $this->items = ($this->searchMediaResources)(
            trim($this->query),
            $this->resolveMediaResourceType(),
        );

        return $this->items;
    }

    /**
     * @return list<MediaResourceType>
     */
    public function getTypeOptions(): array
    {
        return MediaResourceType::cases();
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

    private function resolveMediaResourceType(): ?MediaResourceType
    {
        foreach (MediaResourceType::cases() as $typeOption) {
            if ($typeOption->value === $this->type) {
                return $typeOption;
            }
        }

        return null;
    }
}
