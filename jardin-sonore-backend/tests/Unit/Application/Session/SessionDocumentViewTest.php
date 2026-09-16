<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\SessionDocumentView;
use App\Application\Session\SessionSequenceView;
use App\Application\Session\SessionSummaryView;
use App\Application\Session\YoutubeThumbnailProviderInterface;
use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Model\Session\SessionDocumentStatus;
use App\Domain\Model\Session\SessionSequenceMedia;
use App\Domain\Model\Session\SessionSequenceType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SessionDocumentViewTest extends TestCase
{
    public function testItOnlyRequestsThumbnailsForMediaDisplayedOnTheSession(): void
    {
        $visibleMedia = new SessionSequenceMedia(
            label: 'Comptine filmée',
            type: MediaResourceType::VIDEO,
            url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            imageUrl: null,
            featured: false,
            displayOnSession: true,
        );
        $hiddenMedia = new SessionSequenceMedia(
            label: 'Ressource privée',
            type: MediaResourceType::VIDEO,
            url: 'https://www.youtube.com/watch?v=oHg5SJYRHA0',
            imageUrl: null,
            featured: false,
            displayOnSession: false,
        );
        $sessionSummaryView = $this->sessionSummaryView([$visibleMedia, $hiddenMedia]);
        $youtubeThumbnailProvider = new class implements YoutubeThumbnailProviderInterface {
            /** @var list<string> */
            public array $requestedUrls = [];

            public function getThumbnailDataUri(string $mediaUrl): ?string
            {
                $this->requestedUrls[] = $mediaUrl;

                return 'data:image/jpeg;base64,dmlkZW8=';
            }
        };

        $sessionDocumentView = SessionDocumentView::fromSessionSummaryView($sessionSummaryView, $youtubeThumbnailProvider);

        self::assertSame([$visibleMedia->url], $youtubeThumbnailProvider->requestedUrls);
        self::assertSame('data:image/jpeg;base64,dmlkZW8=', $sessionDocumentView->thumbnailDataUris[$visibleMedia->url]);
        self::assertArrayNotHasKey($hiddenMedia->url, $sessionDocumentView->thumbnailDataUris);
    }

    /**
     * @param list<SessionSequenceMedia> $media
     */
    private function sessionSummaryView(array $media): SessionSummaryView
    {
        $now = new DateTimeImmutable('2026-07-31 09:00:00');

        return new SessionSummaryView(
            uuid: Uuid::v4(),
            title: 'Séance du matin',
            sessionDate: $now,
            organizations: [],
            theme: null,
            generalNotes: null,
            materialSummary: null,
            furtherExploration: null,
            instrumentUuids: [],
            instrumentNames: [],
            recommendations: [],
            recommendationUuids: [],
            sequences: [new SessionSequenceView(
                uuid: Uuid::v4(),
                type: SessionSequenceType::FREE,
                title: 'Jeu d\'écoute',
                subtitle: null,
                body: '',
                lyrics: null,
                gestures: null,
                notes: 'Privé',
                primaryUrl: null,
                secondaryUrl: null,
                imageUrl: null,
                showLyricsByDefault: false,
                role: null,
                sourceUuid: null,
                sourceKind: null,
                sourceTitle: null,
                instrumentUuids: [],
                instrumentNames: [],
                media: $media,
                composerMedia: $media,
            )],
            updatedAt: $now,
            documentStatus: SessionDocumentStatus::PENDING,
            documentPath: null,
            documentError: null,
        );
    }
}
