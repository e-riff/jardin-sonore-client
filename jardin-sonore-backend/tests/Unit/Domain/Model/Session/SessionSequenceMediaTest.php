<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Session;

use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSequenceMedia;
use App\Domain\Model\Session\SessionSequenceType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SessionSequenceMediaTest extends TestCase
{
    #[Test]
    public function itAlwaysDisplaysAFeaturedMedium(): void
    {
        $sessionSequenceMedia = new SessionSequenceMedia(
            label: 'Écouter',
            type: MediaResourceType::SOUNDTRACK,
            url: 'https://example.test/listen',
            imageUrl: null,
            featured: true,
            displayOnSession: false,
        );

        self::assertTrue($sessionSequenceMedia->isDisplayedOnSession());
    }

    #[Test]
    public function itConvertsLegacyUrlsToVisibleMedia(): void
    {
        $sessionSequence = SessionSequence::fromArray([
            'uuid' => Uuid::v4()->toRfc4122(),
            'type' => SessionSequenceType::SOUNDTRACK->value,
            'title' => 'La chanson',
            'body' => 'Écouter et chanter.',
            'primaryUrl' => 'https://example.test/main',
            'secondaryUrl' => 'https://example.test/partition',
            'imageUrl' => 'https://example.test/cover.jpg',
        ]);

        self::assertCount(2, $sessionSequence->getMedia());
        self::assertTrue($sessionSequence->getMedia()[0]->featured);
        self::assertSame('https://example.test/cover.jpg', $sessionSequence->getMedia()[0]->imageUrl);
        self::assertTrue($sessionSequence->getMedia()[1]->isDisplayedOnSession());
    }

    #[Test]
    public function itKeepsTheCatalogUuidAlongsideLegacyDisplayData(): void
    {
        $mediaUuid = Uuid::v4();
        $media = new SessionSequenceMedia(
            label: 'Partition',
            type: MediaResourceType::SCORE,
            url: 'https://example.test/partition.pdf',
            imageUrl: null,
            featured: true,
            displayOnSession: true,
            resourceUuid: $mediaUuid,
        );

        self::assertSame($mediaUuid->toRfc4122(), $media->toArray()['resourceUuid']);
        self::assertTrue($mediaUuid->equals(SessionSequenceMedia::fromArray($media->toArray())->resourceUuid));
    }
}
