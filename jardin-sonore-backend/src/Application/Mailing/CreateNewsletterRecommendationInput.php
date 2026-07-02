<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class CreateNewsletterRecommendationInput
{
    public function __construct(
        public string $title,
        public ?string $tag,
        public string $text,
        public ?string $url,
        public ?string $linkLabel,
        public ?UploadedFile $imageFile,
        public bool $active,
    ) {
    }
}
