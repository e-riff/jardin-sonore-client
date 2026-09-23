<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Storage\PortalAvatarStorageInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class LocalPortalAvatarStorage implements PortalAvatarStorageInterface
{
    private const string PUBLIC_DIRECTORY = 'uploads/portal/avatars';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/' . self::PUBLIC_DIRECTORY)]
        private string $uploadDirectory,
    ) {
    }

    public function store(UploadedFile $uploadedFile): string
    {
        if (!is_dir($this->uploadDirectory) && !mkdir($this->uploadDirectory, 0775, true) && !is_dir($this->uploadDirectory)) {
            throw new RuntimeException("Unable to create portal avatar upload directory {$this->uploadDirectory}.");
        }

        $extension = $uploadedFile->guessExtension();
        if (null === $extension || !in_array($extension, ['jpg', 'png', 'webp'], true)) {
            throw new RuntimeException('Unable to determine a supported avatar image extension.');
        }

        $filename = Uuid::v7()->toRfc4122() . ".{$extension}";
        $uploadedFile->move($this->uploadDirectory, $filename);

        return $filename;
    }
}
