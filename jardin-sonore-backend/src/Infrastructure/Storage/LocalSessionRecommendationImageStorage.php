<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Storage\SessionRecommendationImageStorageInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class LocalSessionRecommendationImageStorage implements SessionRecommendationImageStorageInterface
{
    private const string PUBLIC_DIRECTORY = 'uploads/session/recommendations';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/' . self::PUBLIC_DIRECTORY)]
        private string $uploadDirectory,
    ) {
    }

    public function store(UploadedFile $uploadedFile): string
    {
        if (!is_dir($this->uploadDirectory) && !mkdir($this->uploadDirectory, 0775, true) && !is_dir($this->uploadDirectory)) {
            throw new RuntimeException("Unable to create session recommendation upload directory {$this->uploadDirectory}.");
        }

        $extension = $uploadedFile->guessExtension();

        if (null === $extension) {
            throw new RuntimeException('Unable to determine session recommendation image extension.');
        }

        $filename = Uuid::v7()->toRfc4122() . ".{$extension}";
        $uploadedFile->move($this->uploadDirectory, $filename);

        return self::PUBLIC_DIRECTORY . "/{$filename}";
    }

    public function delete(string $imagePath): void
    {
        if (!str_starts_with($imagePath, self::PUBLIC_DIRECTORY . '/')) {
            return;
        }
        $filename = substr($imagePath, strlen(self::PUBLIC_DIRECTORY) + 1);
        if (1 !== preg_match('/^[a-f0-9-]{36}\.(?:jpeg|jpg|png|webp)$/D', $filename)) {
            return;
        }
        $path = $this->uploadDirectory . "/{$filename}";
        if (is_file($path)) {
            unlink($path);
        }
    }
}
