<?php

declare(strict_types=1);

namespace App\Application\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface MailingIntroImageStorageInterface
{
    public function store(UploadedFile $uploadedFile): string;

    public function delete(string $storedPath): void;
}
