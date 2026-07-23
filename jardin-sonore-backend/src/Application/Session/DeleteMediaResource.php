<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Application\Storage\MediaResourceFileStorageInterface;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class DeleteMediaResource
{
    public function __construct(
        private MediaResourceRepositoryInterface $mediaResourceRepository,
        private RepertoireItemRepositoryInterface $repertoireItemRepository,
        private MediaResourceFileStorageInterface $mediaResourceFileStorage,
    ) {
    }

    public function __invoke(Uuid $uuid): void
    {
        $mediaResource = $this->mediaResourceRepository->findByUuid($uuid);

        if (null === $mediaResource) {
            throw new InvalidArgumentException('Media resource not found.');
        }

        foreach ($this->repertoireItemRepository->search() as $repertoireItem) {
            $repertoireItem->removeLinkedMedia($uuid);
            $this->repertoireItemRepository->save($repertoireItem);
        }

        $this->mediaResourceRepository->delete($mediaResource);
        $this->mediaResourceFileStorage->delete($mediaResource->getPrimaryUrl());

        if (null !== $mediaResource->getImageUrl()) {
            $this->mediaResourceFileStorage->delete($mediaResource->getImageUrl());
        }
    }
}
