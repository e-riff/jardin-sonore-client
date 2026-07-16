<?php

declare(strict_types=1);

namespace App\Application\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface SessionRecommendationImageStorageInterface
{
    public function store(UploadedFile $uploadedFile): string;
}
