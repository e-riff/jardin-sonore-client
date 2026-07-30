<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSequenceMedia;
use App\Domain\Model\Session\SessionSequenceSourceKind;
use App\Domain\Model\Session\SessionSequenceType;
use Symfony\Component\Uid\Uuid;

final readonly class SessionSequenceView
{
    /**
     * @param list<string>               $instrumentUuids
     * @param list<SessionSequenceMedia> $media
     */
    public function __construct(
        public Uuid $uuid,
        public SessionSequenceType $type,
        public string $title,
        public ?string $subtitle,
        public string $body,
        public ?string $lyrics,
        public ?string $gestures,
        public ?string $notes,
        public ?string $primaryUrl,
        public ?string $secondaryUrl,
        public ?string $imageUrl,
        public bool $showLyricsByDefault,
        public ?string $role,
        public ?Uuid $sourceUuid,
        public ?SessionSequenceSourceKind $sourceKind,
        public ?string $sourceTitle,
        public array $instrumentUuids,
        public array $media,
    ) {
    }

    public static function fromDomain(SessionSequence $sessionSequence): self
    {
        $media = $sessionSequence->getMedia();
        $featuredMedia = current(array_filter($media, static fn (SessionSequenceMedia $sessionSequenceMedia): bool => $sessionSequenceMedia->featured));
        $visibleNonFeaturedMedia = array_values(array_filter($media, static fn (SessionSequenceMedia $sessionSequenceMedia): bool => !$sessionSequenceMedia->featured && $sessionSequenceMedia->isDisplayedOnSession()));

        return new self(
            uuid: $sessionSequence->uuid,
            type: $sessionSequence->type,
            title: $sessionSequence->title,
            subtitle: $sessionSequence->subtitle,
            body: $sessionSequence->body,
            lyrics: $sessionSequence->lyrics,
            gestures: $sessionSequence->gestures,
            notes: $sessionSequence->notes,
            primaryUrl: $featuredMedia instanceof SessionSequenceMedia ? $featuredMedia->url : null,
            secondaryUrl: $visibleNonFeaturedMedia[0]->url ?? null,
            imageUrl: $featuredMedia instanceof SessionSequenceMedia ? $featuredMedia->imageUrl : null,
            showLyricsByDefault: $sessionSequence->showLyricsByDefault,
            role: $sessionSequence->role,
            sourceUuid: $sessionSequence->sourceUuid,
            sourceKind: $sessionSequence->sourceKind,
            sourceTitle: $sessionSequence->sourceTitle,
            instrumentUuids: $sessionSequence->instrumentUuids,
            media: $media,
        );
    }
}
