<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\MediaResourceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetMediaResourceForEdit
{
    public function __construct(private MediaResourceRepositoryInterface $mediaResourceRepository)
    {
    }

    public function __invoke(Uuid $uuid): ?MediaResourceView
    {
        $mediaResource = $this->mediaResourceRepository->findByUuid($uuid);

        return null === $mediaResource ? null : MediaResourceView::fromDomain($mediaResource);
    }
}
