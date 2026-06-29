<?php

declare(strict_types=1);

namespace App\Application\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface RecommendationImageStorageInterface
{
    public function store(UploadedFile $uploadedFile): string;
}
