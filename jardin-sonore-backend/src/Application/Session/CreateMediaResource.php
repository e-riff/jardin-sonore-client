<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Application\Storage\MediaResourceFileStorageInterface;
use App\Domain\Model\Session\MediaResource;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use App\Domain\Repository\ThemeRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CreateMediaResource
{
    public function __construct(
        private MediaResourceRepositoryInterface $mediaResourceRepository,
        private MediaResourceFileStorageInterface $mediaResourceFileStorage,
        private ThemeRepositoryInterface $themeRepository,
    ) {
    }

    public function __invoke(SaveMediaResourceInput $saveMediaResourceInput): MediaResource
    {
        $primaryUrl = null === $saveMediaResourceInput->primaryFile
            ? trim((string) $saveMediaResourceInput->primaryUrl)
            : $this->mediaResourceFileStorage->storePrimaryFile($saveMediaResourceInput->primaryFile);
        $imageUrl = null === $saveMediaResourceInput->imageFile
            ? $saveMediaResourceInput->imageUrl
            : $this->mediaResourceFileStorage->storeImageFile($saveMediaResourceInput->imageFile);

        $mediaResource = new MediaResource(
            type: $saveMediaResourceInput->type,
            title: $saveMediaResourceInput->title,
            primaryUrl: $primaryUrl,
            source: $saveMediaResourceInput->source,
            description: $saveMediaResourceInput->description,
            secondaryUrl: $saveMediaResourceInput->secondaryUrl,
            imageUrl: $imageUrl,
            active: $saveMediaResourceInput->active,
            themes: array_values(array_filter(array_map(fn (string $themeUuid) => Uuid::isValid($themeUuid) ? $this->themeRepository->findByUuid(Uuid::fromString($themeUuid)) : null, array_unique($saveMediaResourceInput->themeUuids)))),
        );

        $this->mediaResourceRepository->save($mediaResource);

        return $mediaResource;
    }
}
