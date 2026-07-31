<?php

declare(strict_types=1);

namespace App\Application\Session;

interface YoutubeThumbnailProviderInterface
{
    public function getThumbnailDataUri(string $mediaUrl): ?string;
}
