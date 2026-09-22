<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Application\Session\YoutubeThumbnailDownloaderInterface;
use App\Application\Session\YoutubeThumbnailProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class CachedYoutubeThumbnailProvider implements YoutubeThumbnailProviderInterface
{
    private const int CACHE_TTL_SECONDS = 2_592_000;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/session-document-thumbnails')]
        private string $cacheDirectory,
        private YoutubeThumbnailDownloaderInterface $youtubeThumbnailDownloader,
    ) {
    }

    public function getThumbnailDataUri(string $mediaUrl): ?string
    {
        $youtubeVideoId = $this->extractYoutubeVideoId($mediaUrl);

        if (null === $youtubeVideoId) {
            return null;
        }

        $thumbnailPath = $this->cacheDirectory . "/{$youtubeVideoId}.jpg";
        $thumbnailBytes = $this->readFreshThumbnail($thumbnailPath);

        if (null === $thumbnailBytes) {
            $thumbnailBytes = $this->youtubeThumbnailDownloader->download($youtubeVideoId);

            if (null === $thumbnailBytes || !$this->isJpeg($thumbnailBytes)) {
                return null;
            }

            $this->storeThumbnail($thumbnailPath, $thumbnailBytes);
        }

        return 'data:image/jpeg;base64,' . base64_encode($thumbnailBytes);
    }

    private function extractYoutubeVideoId(string $mediaUrl): ?string
    {
        $urlParts = parse_url($mediaUrl);

        if (false === $urlParts || !isset($urlParts['scheme'], $urlParts['host']) || 'https' !== strtolower($urlParts['scheme'])) {
            return null;
        }

        $host = strtolower($urlParts['host']);
        $youtubeVideoId = match ($host) {
            'youtu.be' => ltrim($urlParts['path'] ?? '', '/'),
            'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'www.youtube-nocookie.com' => $this->youtubeVideoIdFromYoutubePath($urlParts),
            default => null,
        };

        if (!is_string($youtubeVideoId) || 1 !== preg_match('/^[A-Za-z0-9_-]{11}$/', $youtubeVideoId)) {
            return null;
        }

        return $youtubeVideoId;
    }

    /** @param array<string, int|string> $urlParts */
    private function youtubeVideoIdFromYoutubePath(array $urlParts): ?string
    {
        $path = $urlParts['path'] ?? '';

        if ('/watch' === $path && isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParameters);

            return is_string($queryParameters['v'] ?? null) ? $queryParameters['v'] : null;
        }

        if (1 === preg_match('#^/embed/([A-Za-z0-9_-]{11})$#', $path, $matches)) {
            return $matches[1];
        }

        if (1 === preg_match('#^/(?:shorts|live)/([A-Za-z0-9_-]{11})$#', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function readFreshThumbnail(string $thumbnailPath): ?string
    {
        $modifiedAt = @filemtime($thumbnailPath);

        if (false === $modifiedAt || $modifiedAt < time() - self::CACHE_TTL_SECONDS) {
            return null;
        }

        $thumbnailBytes = @file_get_contents($thumbnailPath);

        return is_string($thumbnailBytes) && $this->isJpeg($thumbnailBytes) ? $thumbnailBytes : null;
    }

    private function storeThumbnail(string $thumbnailPath, string $thumbnailBytes): void
    {
        if (!is_dir($this->cacheDirectory) && !mkdir($this->cacheDirectory, 0775, true) && !is_dir($this->cacheDirectory)) {
            return;
        }

        @file_put_contents($thumbnailPath, $thumbnailBytes, LOCK_EX);
    }

    private function isJpeg(string $thumbnailBytes): bool
    {
        return str_starts_with($thumbnailBytes, "\xFF\xD8\xFF");
    }
}
