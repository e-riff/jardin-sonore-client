<?php

declare(strict_types=1);

namespace App\Application\Session;

interface YoutubeThumbnailDownloaderInterface
{
    public function download(string $youtubeVideoId): ?string;
}
