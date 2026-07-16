<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Repository\MediaResourceRepositoryInterface;

final readonly class SearchMediaResources
{
    public function __construct(private MediaResourceRepositoryInterface $mediaResourceRepository)
    {
    }

    /** @return list<MediaResourceView> */
    public function __invoke(?string $query = null, ?MediaResourceType $mediaResourceType = null, bool $activeOnly = false): array
    {
        return array_map(
            static fn ($mediaResource): MediaResourceView => MediaResourceView::fromDomain($mediaResource),
            $this->mediaResourceRepository->search($query, $mediaResourceType, $activeOnly),
        );
    }
}
