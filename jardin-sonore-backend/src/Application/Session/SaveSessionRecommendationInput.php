<?php

declare(strict_types=1);

namespace App\Application\Session;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class SaveSessionRecommendationInput
{
    public function __construct(
        public string $title,
        public string $text,
        public ?string $notes,
        public ?string $primaryUrl,
        public ?string $secondaryUrl,
        public ?string $imageUrl,
        public ?UploadedFile $imageFile,
        public bool $active,
    ) {
    }
}
