<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class MailingIntroBlockInput
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $text,
        public ?UploadedFile $imageFile,
        public ?string $imageAlt,
        public bool $imageDecorative,
        public string $imageSide,
    ) {
    }
}
