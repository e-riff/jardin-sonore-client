<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Storage\RecommendationImageStorageInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class LocalRecommendationImageStorage implements RecommendationImageStorageInterface
{
    private const string PUBLIC_DIRECTORY = 'uploads/mailing/recommendations';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/' . self::PUBLIC_DIRECTORY)]
        private string $uploadDirectory,
    ) {
    }

    public function store(UploadedFile $uploadedFile): string
    {
        if (!is_dir($this->uploadDirectory) && !mkdir($this->uploadDirectory, 0775, true) && !is_dir($this->uploadDirectory)) {
            throw new RuntimeException("Unable to create recommendation upload directory {$this->uploadDirectory}.");
        }

        $extension = $uploadedFile->guessExtension();

        if (null === $extension) {
            throw new RuntimeException('Unable to determine recommendation image extension.');
        }

        $filename = Uuid::v7()->toRfc4122() . ".{$extension}";
        $uploadedFile->move($this->uploadDirectory, $filename);

        return self::PUBLIC_DIRECTORY . "/{$filename}";
    }
}
