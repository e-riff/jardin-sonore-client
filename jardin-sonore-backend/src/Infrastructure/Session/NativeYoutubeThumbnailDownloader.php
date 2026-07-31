<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Application\Session\YoutubeThumbnailDownloaderInterface;

final class NativeYoutubeThumbnailDownloader implements YoutubeThumbnailDownloaderInterface
{
    public function download(string $youtubeVideoId): ?string
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{11}$/', $youtubeVideoId)) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'JardinSonoreSessionDocument/1.0',
            ],
        ]);
        $thumbnailBytes = @file_get_contents("https://i.ytimg.com/vi/{$youtubeVideoId}/hqdefault.jpg", false, $context);

        return is_string($thumbnailBytes) && str_starts_with($thumbnailBytes, "\xFF\xD8\xFF") ? $thumbnailBytes : null;
    }
}
