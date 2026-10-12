<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Storage\MailingIntroImageStorageInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class LocalMailingIntroImageStorage implements MailingIntroImageStorageInterface
{
    private const string PUBLIC_DIRECTORY = 'uploads/mailing/content';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/' . self::PUBLIC_DIRECTORY)]
        private string $uploadDirectory,
    ) {
    }

    public function store(UploadedFile $uploadedFile): string
    {
        if (!is_dir($this->uploadDirectory) && !mkdir($this->uploadDirectory, 0775, true) && !is_dir($this->uploadDirectory)) {
            throw new RuntimeException("Unable to create mailing introduction image directory {$this->uploadDirectory}.");
        }

        $extension = $uploadedFile->guessExtension();
        if (null === $extension) {
            throw new RuntimeException('Unable to determine mailing introduction image extension.');
        }

        $filename = Uuid::v7()->toRfc4122() . ".{$extension}";
        $uploadedFile->move($this->uploadDirectory, $filename);

        return self::PUBLIC_DIRECTORY . "/{$filename}";
    }

    public function delete(string $storedPath): void
    {
        $prefix = self::PUBLIC_DIRECTORY . '/';
        if (!str_starts_with($storedPath, $prefix)) {
            return;
        }

        $filename = substr($storedPath, strlen($prefix));
        if ('' === $filename || basename($filename) !== $filename) {
            return;
        }

        $path = $this->uploadDirectory . '/' . $filename;
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException("Unable to delete mailing introduction image {$path}.");
        }
    }
}
