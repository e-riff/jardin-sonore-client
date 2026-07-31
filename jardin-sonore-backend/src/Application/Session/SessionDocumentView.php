<?php

declare(strict_types=1);

namespace App\Application\Session;

final readonly class SessionDocumentView
{
    /**
     * @param array<string, string> $thumbnailDataUris
     */
    public function __construct(
        public SessionSummaryView $session,
        public array $thumbnailDataUris,
    ) {
    }

    public static function fromSessionSummaryView(
        SessionSummaryView $sessionSummaryView,
        YoutubeThumbnailProviderInterface $youtubeThumbnailProvider,
    ): self {
        $thumbnailDataUris = [];

        foreach ($sessionSummaryView->sequences as $sessionSequenceView) {
            foreach ($sessionSequenceView->media as $sessionSequenceMedia) {
                if (!$sessionSequenceMedia->isDisplayedOnSession()) {
                    continue;
                }

                $thumbnailDataUri = $youtubeThumbnailProvider->getThumbnailDataUri($sessionSequenceMedia->url);
                if (null !== $thumbnailDataUri) {
                    $thumbnailDataUris[$sessionSequenceMedia->url] = $thumbnailDataUri;
                }
            }
        }

        return new self($sessionSummaryView, $thumbnailDataUris);
    }
}
