<?php

declare(strict_types=1);

namespace App\Application\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface MediaResourceFileStorageInterface
{
    public function storePrimaryFile(UploadedFile $uploadedFile): string;

    public function storeImageFile(UploadedFile $uploadedFile): string;
}
