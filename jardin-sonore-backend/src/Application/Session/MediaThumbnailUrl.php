<?php

declare(strict_types=1);

namespace App\Application\Session;

final class MediaThumbnailUrl
{
    public static function fromUrl(string $url, ?string $imageUrl = null): ?string
    {
        if (null !== $imageUrl && '' !== trim($imageUrl)) {
            return $imageUrl;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        $videoId = null;

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $videoId = trim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if (preg_match('~^/(?:shorts|embed)/([^/]+)~', $path, $matches)) {
                $videoId = $matches[1];
            } else {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $videoId = $query['v'] ?? null;
            }
        }

        return is_string($videoId) && 1 === preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)
            ? "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg"
            : null;
    }
}
