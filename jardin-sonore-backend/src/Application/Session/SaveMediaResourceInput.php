<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\MediaResourceType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class SaveMediaResourceInput
{
    public function __construct(
        public MediaResourceType $type,
        public string $title,
        public ?string $primaryUrl,
        public ?UploadedFile $primaryFile,
        public ?string $source,
        public ?string $description,
        public ?string $secondaryUrl,
        public ?string $imageUrl,
        public ?UploadedFile $imageFile,
        public bool $active,
    ) {
    }
}
