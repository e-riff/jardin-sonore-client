<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Storage\MediaResourceFileStorageInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class LocalMediaResourceFileStorage implements MediaResourceFileStorageInterface
{
    private const string PRIMARY_PUBLIC_DIRECTORY = 'uploads/media/resources';
    private const string IMAGE_PUBLIC_DIRECTORY = 'uploads/media/images';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/' . self::PRIMARY_PUBLIC_DIRECTORY)]
        private string $primaryUploadDirectory,
        #[Autowire('%kernel.project_dir%/public/' . self::IMAGE_PUBLIC_DIRECTORY)]
        private string $imageUploadDirectory,
    ) {
    }

    public function storePrimaryFile(UploadedFile $uploadedFile): string
    {
        return $this->store($uploadedFile, $this->primaryUploadDirectory, self::PRIMARY_PUBLIC_DIRECTORY, 'media resource');
    }

    public function storeImageFile(UploadedFile $uploadedFile): string
    {
        return $this->store($uploadedFile, $this->imageUploadDirectory, self::IMAGE_PUBLIC_DIRECTORY, 'media image');
    }

    public function delete(string $storedPath): void
    {
        foreach ([self::PRIMARY_PUBLIC_DIRECTORY => $this->primaryUploadDirectory, self::IMAGE_PUBLIC_DIRECTORY => $this->imageUploadDirectory] as $publicDirectory => $uploadDirectory) {
            $prefix = $publicDirectory . '/';

            if (!str_starts_with($storedPath, $prefix)) {
                continue;
            }

            $filename = substr($storedPath, strlen($prefix));

            if ('' === $filename || basename($filename) !== $filename) {
                return;
            }

            $path = $uploadDirectory . '/' . $filename;

            if (is_file($path)) {
                unlink($path);
            }

            return;
        }
    }

    private function store(UploadedFile $uploadedFile, string $uploadDirectory, string $publicDirectory, string $label): string
    {
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException("Unable to create {$label} upload directory {$uploadDirectory}.");
        }

        $extension = $uploadedFile->guessExtension();

        if (null === $extension) {
            throw new RuntimeException("Unable to determine {$label} extension.");
        }

        $filename = Uuid::v7()->toRfc4122() . ".{$extension}";
        $uploadedFile->move($uploadDirectory, $filename);

        return $publicDirectory . "/{$filename}";
    }
}
