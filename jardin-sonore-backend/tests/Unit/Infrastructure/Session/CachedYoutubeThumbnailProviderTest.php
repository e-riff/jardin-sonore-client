<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Session;

use App\Application\Session\YoutubeThumbnailDownloaderInterface;
use App\Application\Session\YoutubeThumbnailProviderInterface;
use App\Infrastructure\Session\CachedYoutubeThumbnailProvider;
use App\Infrastructure\Session\NativeYoutubeThumbnailDownloader;
use LogicException;
use PHPUnit\Framework\TestCase;

final class CachedYoutubeThumbnailProviderTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/jardin-sonore-youtube-thumbnails-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (is_file($this->cacheDirectory . '/dQw4w9WgXcQ.jpg')) {
            unlink($this->cacheDirectory . '/dQw4w9WgXcQ.jpg');
        }

        if (is_dir($this->cacheDirectory)) {
            rmdir($this->cacheDirectory);
        }
    }

    public function testDownloadsAndCachesThumbnailForAValidYoutubeVideoUrl(): void
    {
        $thumbnailBytes = "\xFF\xD8\xFF\xE0thumbnail";
        $downloader = new class($thumbnailBytes) implements YoutubeThumbnailDownloaderInterface {
            public int $calls = 0;

            private string $thumbnailBytes;

            public function __construct(string $thumbnailBytes)
            {
                $this->thumbnailBytes = $thumbnailBytes;
            }

            public function download(string $youtubeVideoId): ?string
            {
                ++$this->calls;

                if ('' === $youtubeVideoId) {
                    return null;
                }

                return $this->thumbnailBytes;
            }
        };
        $thumbnailProvider = new CachedYoutubeThumbnailProvider($this->cacheDirectory, $downloader);

        self::assertInstanceOf(YoutubeThumbnailProviderInterface::class, $thumbnailProvider);

        $thumbnailDataUri = $thumbnailProvider->getThumbnailDataUri('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        self::assertSame('data:image/jpeg;base64,' . base64_encode($thumbnailBytes), $thumbnailDataUri);
        self::assertSame(1, $downloader->calls);
        self::assertSame($thumbnailBytes, file_get_contents($this->cacheDirectory . '/dQw4w9WgXcQ.jpg'));
    }

    public function testNativeDownloaderRejectsAnInvalidYoutubeVideoIdWithoutMakingARequest(): void
    {
        $thumbnailDownloader = new NativeYoutubeThumbnailDownloader();

        self::assertNull($thumbnailDownloader->download('../not-a-youtube-video-id'));
    }

    public function testUsesTheFreshCachedThumbnailWithoutDownloadingItAgain(): void
    {
        mkdir($this->cacheDirectory, 0775, true);
        $thumbnailBytes = "\xFF\xD8\xFF\xE0cached-thumbnail";
        file_put_contents($this->cacheDirectory . '/dQw4w9WgXcQ.jpg', $thumbnailBytes);
        $downloader = new class implements YoutubeThumbnailDownloaderInterface {
            public function download(string $youtubeVideoId): ?string
            {
                throw new LogicException('A fresh local thumbnail must be reused.');
            }
        };
        $thumbnailProvider = new CachedYoutubeThumbnailProvider($this->cacheDirectory, $downloader);

        $thumbnailDataUri = $thumbnailProvider->getThumbnailDataUri('https://youtu.be/dQw4w9WgXcQ');

        self::assertSame('data:image/jpeg;base64,' . base64_encode($thumbnailBytes), $thumbnailDataUri);
    }

    public function testRefreshesAThumbnailCachedForMoreThanThirtyDays(): void
    {
        mkdir($this->cacheDirectory, 0775, true);
        file_put_contents($this->cacheDirectory . '/dQw4w9WgXcQ.jpg', "\xFF\xD8\xFF\xE0stale-thumbnail");
        touch($this->cacheDirectory . '/dQw4w9WgXcQ.jpg', time() - 2_592_001);
        $newThumbnailBytes = "\xFF\xD8\xFF\xE0new-thumbnail";
        $downloader = new class($newThumbnailBytes) implements YoutubeThumbnailDownloaderInterface {
            public function __construct(private readonly string $newThumbnailBytes)
            {
            }

            public function download(string $youtubeVideoId): ?string
            {
                if ('' === $youtubeVideoId) {
                    return null;
                }

                return $this->newThumbnailBytes;
            }
        };
        $thumbnailProvider = new CachedYoutubeThumbnailProvider($this->cacheDirectory, $downloader);

        $thumbnailDataUri = $thumbnailProvider->getThumbnailDataUri('https://www.youtube.com/embed/dQw4w9WgXcQ');

        self::assertSame('data:image/jpeg;base64,' . base64_encode($newThumbnailBytes), $thumbnailDataUri);
        self::assertSame($newThumbnailBytes, file_get_contents($this->cacheDirectory . '/dQw4w9WgXcQ.jpg'));
    }

    public function testRejectsUrlsOutsideTheStrictYoutubeFormats(): void
    {
        $downloader = new class implements YoutubeThumbnailDownloaderInterface {
            public function download(string $youtubeVideoId): ?string
            {
                throw new LogicException('An unsupported URL must not trigger a download.');
            }
        };
        $thumbnailProvider = new CachedYoutubeThumbnailProvider($this->cacheDirectory, $downloader);

        self::assertNull($thumbnailProvider->getThumbnailDataUri('https://youtube.example/watch?v=dQw4w9WgXcQ'));
        self::assertNull($thumbnailProvider->getThumbnailDataUri('http://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        self::assertNull($thumbnailProvider->getThumbnailDataUri('https://www.youtube.com/watch?v=invalid'));
        self::assertNull($thumbnailProvider->getThumbnailDataUri('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
    }
}
