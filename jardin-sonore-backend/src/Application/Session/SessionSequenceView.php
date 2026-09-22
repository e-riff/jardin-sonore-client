<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSequenceMedia;
use App\Domain\Model\Session\SessionSequenceSourceKind;
use App\Domain\Model\Session\SessionSequenceType;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class SessionSequenceView
{
    /**
     * @param list<string>               $instrumentUuids
     * @param list<string>               $instrumentNames
     * @param list<SessionSequenceMedia> $media
     * @param list<SessionSequenceMedia> $composerMedia
     * @param list<SessionSequenceMedia> $documentMedia
     * @param list<RepertoireBlockView>  $contentBlocks
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
        public array $instrumentNames,
        public array $media,
        public array $composerMedia,
        public array $documentMedia = [],
        public ?string $generalInstructions = null,
        public array $contentBlocks = [],
    ) {
    }

    public static function fromDomain(
        SessionSequence $sessionSequence,
        ?RepertoireItem $repertoireItem = null,
        ?InstrumentRepositoryInterface $instrumentRepository = null,
        ?MediaResourceRepositoryInterface $mediaResourceRepository = null,
    ): self {
        $media = $sessionSequence->getMedia();
        $featuredMedia = current(array_filter($media, static fn (SessionSequenceMedia $sessionSequenceMedia): bool => $sessionSequenceMedia->featured));
        $visibleNonFeaturedMedia = array_values(array_filter($media, static fn (SessionSequenceMedia $sessionSequenceMedia): bool => !$sessionSequenceMedia->featured && $sessionSequenceMedia->isDisplayedOnSession()));
        $isSynchronizedRepertoireItem = $repertoireItem instanceof RepertoireItem
            && SessionSequenceSourceKind::REPERTOIRE_ITEM === $sessionSequence->sourceKind
            && null !== $sessionSequence->sourceUuid
            && $sessionSequence->sourceUuid->equals($repertoireItem->getUuid());
        $linkedMedia = [];

        if ($isSynchronizedRepertoireItem && null !== $mediaResourceRepository) {
            foreach ($repertoireItem->getLinkedMediaUuids() as $linkedMediaUuid) {
                if (!Uuid::isValid($linkedMediaUuid)) {
                    continue;
                }

                $mediaResource = $mediaResourceRepository->findByUuid(Uuid::fromString($linkedMediaUuid));
                if (null === $mediaResource || !$mediaResource->isActive()) {
                    continue;
                }

                $linkedMedia[] = new SessionSequenceMedia(
                    label: $mediaResource->getTitle(),
                    type: $mediaResource->getType(),
                    url: $mediaResource->getPrimaryUrl(),
                    imageUrl: $mediaResource->getImageUrl(),
                    featured: [] === $media && [] === $linkedMedia,
                    displayOnSession: true,
                );
            }
        }

        return new self(
            uuid: $sessionSequence->uuid,
            type: $sessionSequence->type,
            title: $isSynchronizedRepertoireItem ? $repertoireItem->getTitle() : $sessionSequence->title,
            subtitle: $isSynchronizedRepertoireItem ? $repertoireItem->getSource() : $sessionSequence->subtitle,
            body: $sessionSequence->body,
            lyrics: $isSynchronizedRepertoireItem ? $repertoireItem->getLyrics() : $sessionSequence->lyrics,
            gestures: $isSynchronizedRepertoireItem ? $repertoireItem->getGestures() : $sessionSequence->gestures,
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
            instrumentNames: null === $instrumentRepository ? [] : array_values(array_filter(array_map(
                static fn (string $instrumentUuid): ?string => Uuid::isValid($instrumentUuid)
                    ? $instrumentRepository->findByUuid(Uuid::fromString($instrumentUuid))?->getName()
                    : null,
                $sessionSequence->instrumentUuids,
            ))),
            media: $media,
            composerMedia: null === $sessionSequence->sourceKind
                ? $media
                : array_values(array_filter(
                    $media,
                    static fn (SessionSequenceMedia $sessionSequenceMedia): bool => $sessionSequenceMedia->isDisplayedOnSession(),
                )),
            documentMedia: array_merge($linkedMedia, $media),
            generalInstructions: $isSynchronizedRepertoireItem ? $repertoireItem->getGeneralInstructions() : null,
            contentBlocks: $isSynchronizedRepertoireItem
                ? array_map(RepertoireBlockView::fromDomain(...), $repertoireItem->getContentBlocks())
                : [],
        );
    }
}
