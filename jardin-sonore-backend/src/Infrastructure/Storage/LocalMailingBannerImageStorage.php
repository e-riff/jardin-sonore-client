<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Storage\MailingBannerImageStorageInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class LocalMailingBannerImageStorage implements MailingBannerImageStorageInterface
{
    private const string PUBLIC_DIRECTORY = 'uploads/mailing/banners';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/' . self::PUBLIC_DIRECTORY)]
        private string $uploadDirectory,
    ) {
    }

    public function store(UploadedFile $uploadedFile): string
    {
        if (!is_dir($this->uploadDirectory) && !mkdir($this->uploadDirectory, 0775, true) && !is_dir($this->uploadDirectory)) {
            throw new RuntimeException("Unable to create mailing banner upload directory {$this->uploadDirectory}.");
        }

        $extension = $uploadedFile->guessExtension();

        if (null === $extension) {
            throw new RuntimeException('Unable to determine mailing banner image extension.');
        }

        $filename = Uuid::v7()->toRfc4122() . ".{$extension}";
        $uploadedFile->move($this->uploadDirectory, $filename);

        return self::PUBLIC_DIRECTORY . "/{$filename}";
    }

    public function delete(?string $storedPath): void
    {
        $storedPath = null === $storedPath ? null : trim($storedPath);

        if (null === $storedPath || '' === $storedPath) {
            return;
        }

        $normalizedPath = ltrim($storedPath, '/');
        $prefix = self::PUBLIC_DIRECTORY . '/';

        if (!str_starts_with($normalizedPath, $prefix)) {
            return;
        }

        $absolutePath = dirname($this->uploadDirectory) . '/' . $normalizedPath;

        if (is_file($absolutePath) && !unlink($absolutePath)) {
            throw new RuntimeException("Unable to delete mailing banner image {$absolutePath}.");
        }
    }
}
