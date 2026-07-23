<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Application\Storage\MediaResourceFileStorageInterface;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use App\Domain\Repository\ThemeRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateMediaResource
{
    public function __construct(
        private MediaResourceRepositoryInterface $mediaResourceRepository,
        private MediaResourceFileStorageInterface $mediaResourceFileStorage,
        private ThemeRepositoryInterface $themeRepository,
    ) {
    }

    public function __invoke(Uuid $uuid, SaveMediaResourceInput $saveMediaResourceInput): void
    {
        $mediaResource = $this->mediaResourceRepository->findByUuid($uuid);

        if (null === $mediaResource) {
            throw new InvalidArgumentException('Media resource not found.');
        }

        $primaryUrl = null === $saveMediaResourceInput->primaryFile
            ? trim((string) $saveMediaResourceInput->primaryUrl)
            : $this->mediaResourceFileStorage->storePrimaryFile($saveMediaResourceInput->primaryFile);
        $imageUrl = null === $saveMediaResourceInput->imageFile
            ? $saveMediaResourceInput->imageUrl
            : $this->mediaResourceFileStorage->storeImageFile($saveMediaResourceInput->imageFile);

        $mediaResource->updateContent(
            title: $saveMediaResourceInput->title,
            primaryUrl: $primaryUrl,
            source: $saveMediaResourceInput->source,
            description: $saveMediaResourceInput->description,
            secondaryUrl: $saveMediaResourceInput->secondaryUrl,
            imageUrl: $imageUrl,
        );
        $mediaResource->setActive($saveMediaResourceInput->active);
        $mediaResource->setThemes(array_values(array_filter(array_map(fn (string $themeUuid) => Uuid::isValid($themeUuid) ? $this->themeRepository->findByUuid(Uuid::fromString($themeUuid)) : null, array_unique($saveMediaResourceInput->themeUuids)))));

        $this->mediaResourceRepository->save($mediaResource);
    }
}
