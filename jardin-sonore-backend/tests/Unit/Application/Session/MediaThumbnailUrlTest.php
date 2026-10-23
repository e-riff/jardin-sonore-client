<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\MediaThumbnailUrl;
use PHPUnit\Framework\TestCase;

final class MediaThumbnailUrlTest extends TestCase
{
    public function testYoutubeWatchAndShortLinksProduceThumbnail(): void
    {
        self::assertSame('https://img.youtube.com/vi/IWVj7UJ_Rr8/mqdefault.jpg', MediaThumbnailUrl::fromUrl('https://www.youtube.com/watch?v=IWVj7UJ_Rr8'));
        self::assertSame('https://img.youtube.com/vi/IWVj7UJ_Rr8/mqdefault.jpg', MediaThumbnailUrl::fromUrl('https://youtu.be/IWVj7UJ_Rr8'));
    }

    public function testImageTakesPriorityAndOtherHostsAreIgnored(): void
    {
        self::assertSame('/uploads/media/cover.jpg', MediaThumbnailUrl::fromUrl('https://www.youtube.com/watch?v=IWVj7UJ_Rr8', '/uploads/media/cover.jpg'));
        self::assertNull(MediaThumbnailUrl::fromUrl('https://example.org/watch?v=IWVj7UJ_Rr8'));
    }
}
