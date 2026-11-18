<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\SessionSequenceView;
use App\Domain\Model\ContentCatalog\Instrument;
use App\Domain\Model\Session\MediaResource;
use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Model\Session\RepertoireBlock;
use App\Domain\Model\Session\RepertoireBlockKind;
use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSequenceMedia;
use App\Domain\Model\Session\SessionSequenceSourceKind;
use App\Domain\Model\Session\SessionSequenceType;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SessionSequenceViewTest extends TestCase
{
    /** @return iterable<string, array{?string, ?string, list<array{string, ?string, ?string}>}> */
    public static function localLyricsAndGestures(): iterable
    {
        yield 'paired lines' => ["Bonjour\nAu revoir", "Lever les mains\nLes baisser", [['line', 'Bonjour', 'Lever les mains'], ['line', 'Au revoir', 'Les baisser']]];
        yield 'empty gesture line keeps its position' => ["Un\nDeux\nTrois", "Un geste\n\nTrois gestes", [['line', 'Un', 'Un geste'], ['line', 'Deux', null], ['line', 'Trois', 'Trois gestes']]];
        yield 'empty lyric line keeps its position' => ["Un\n\nTrois", "Premier\nDeuxième\nTroisième", [['line', 'Un', 'Premier'], ['line', null, 'Deuxième'], ['line', 'Trois', 'Troisième']]];
        yield 'blank pair becomes a pause' => ["Un\n\nTrois", "Premier\n\nTroisième", [['line', 'Un', 'Premier'], ['break', null, null], ['line', 'Trois', 'Troisième']]];
        yield 'extra lyrics' => ["Un\nDeux", 'Premier', [['line', 'Un', 'Premier'], ['line', 'Deux', null]]];
        yield 'extra gestures' => ['Un', "Premier\nDeuxième", [['line', 'Un', 'Premier'], ['line', null, 'Deuxième']]];
        yield 'gestures only' => [null, "Premier\nDeuxième", [['line', null, 'Premier'], ['line', null, 'Deuxième']]];
        yield 'lyrics only with different newline formats' => ["Un\r\nDeux\rTrois", null, [['line', 'Un', null], ['line', 'Deux', null], ['line', 'Trois', null]]];
        yield 'mixed newline formats' => ["Un\r\nDeux", "Premier\rDeuxième", [['line', 'Un', 'Premier'], ['line', 'Deux', 'Deuxième']]];
        yield 'first gesture intentionally omitted' => ["Un\nDeux", "\nSecond", [['line', 'Un', null], ['line', 'Deux', 'Second']]];
        yield 'absent texts' => [null, null, []];
        yield 'whitespace texts' => [" \n ", "\t", []];
        yield 'leading and trailing blank lines' => ["\nUn\n", "\nPremier\n", [['break', null, null], ['line', 'Un', 'Premier'], ['break', null, null]]];
        yield 'zero is content' => ['0', '0', [['line', '0', '0']]];
    }

    /** @param list<array{string, ?string, ?string}> $expectedBlocks */
    #[DataProvider('localLyricsAndGestures')]
    public function testLocalTextsArePairedWithoutChangingTheStoredContent(?string $lyrics, ?string $gestures, array $expectedBlocks): void
    {
        $sequence = new SessionSequence(
            uuid: Uuid::v4(), type: SessionSequenceType::FREE, title: 'Élément libre', subtitle: null,
            body: 'Préparer les foulards.', lyrics: $lyrics, gestures: $gestures,
            primaryUrl: null, secondaryUrl: null, imageUrl: null, showLyricsByDefault: true,
        );
        $view = SessionSequenceView::fromDomain($sequence);
        self::assertSame($expectedBlocks, array_map(static fn ($block): array => [$block->kind->value, $block->text, $block->gesture], $view->contentBlocks));
        self::assertSame($lyrics, $view->lyrics);
        self::assertSame($gestures, $view->gestures);
        self::assertSame($lyrics, $sequence->lyrics);
        self::assertSame($gestures, $sequence->gestures);
        $rehydratedView = SessionSequenceView::fromDomain(SessionSequence::fromArray($sequence->toArray()));
        self::assertSame($expectedBlocks, array_map(static fn ($block): array => [$block->kind->value, $block->text, $block->gesture], $rehydratedView->contentBlocks));
    }

    public function testFreeSequenceReadsCurrentCatalogMediaAndHidesItWhenGloballyInactive(): void
    {
        $catalogMedia = new MediaResource(
            type: MediaResourceType::SCORE,
            title: 'Partition corrigée',
            primaryUrl: 'https://example.test/new.pdf',
        );
        $oldSnapshot = new SessionSequenceMedia(
            label: 'Ancienne partition',
            type: MediaResourceType::LINK,
            url: 'https://example.test/old.pdf',
            imageUrl: null,
            featured: true,
            displayOnSession: true,
            resourceUuid: $catalogMedia->getUuid(),
        );
        $sequence = new SessionSequence(
            uuid: Uuid::v4(),
            type: SessionSequenceType::FREE,
            title: 'Écoute',
            subtitle: null,
            body: '',
            lyrics: null,
            gestures: null,
            primaryUrl: null,
            secondaryUrl: null,
            imageUrl: null,
            showLyricsByDefault: false,
            media: [$oldSnapshot],
        );
        $repository = new class($catalogMedia) implements MediaResourceRepositoryInterface {
            public function __construct(public MediaResource $media)
            {
            }

            public function findByUuid(Uuid $uuid): ?MediaResource
            {
                return $this->media->getUuid()->equals($uuid) ? $this->media : null;
            }

            public function search(?string $query = null, ?MediaResourceType $mediaResourceType = null, bool $activeOnly = false): array
            {
                return [];
            }

            public function save(MediaResource $mediaResource): void
            {
            }

            public function delete(MediaResource $mediaResource): void
            {
            }
        };

        $view = SessionSequenceView::fromDomain($sequence, mediaResourceRepository: $repository);
        self::assertSame('Partition corrigée', $view->documentMedia[0]->label);
        self::assertSame('https://example.test/new.pdf', $view->documentMedia[0]->url);

        $catalogMedia->setActive(false);
        $hiddenView = SessionSequenceView::fromDomain($sequence, mediaResourceRepository: $repository);
        self::assertSame([], $hiddenView->documentMedia);
    }

    public function testLegacyUrlSnapshotsAreReconciledToTheCatalogWhenTheMatchIsUnique(): void
    {
        $catalogMedia = new MediaResource(
            type: MediaResourceType::SCORE,
            title: 'Partition actuelle',
            primaryUrl: 'https://example.test/legacy-score.pdf',
        );
        $legacySnapshot = new SessionSequenceMedia(
            label: 'Ancien titre copié',
            type: MediaResourceType::LINK,
            url: $catalogMedia->getPrimaryUrl(),
            imageUrl: null,
            featured: true,
            displayOnSession: true,
        );
        $sequence = new SessionSequence(
            uuid: Uuid::v4(),
            type: SessionSequenceType::FREE,
            title: 'Écoute',
            subtitle: null,
            body: '',
            lyrics: null,
            gestures: null,
            primaryUrl: null,
            secondaryUrl: null,
            imageUrl: null,
            showLyricsByDefault: false,
            media: [$legacySnapshot],
        );
        $repository = new class($catalogMedia) implements MediaResourceRepositoryInterface {
            public function __construct(private MediaResource $media)
            {
            }

            public function findByUuid(Uuid $uuid): ?MediaResource
            {
                return $this->media->getUuid()->equals($uuid) ? $this->media : null;
            }

            public function search(?string $query = null, ?MediaResourceType $mediaResourceType = null, bool $activeOnly = false): array
            {
                return [$this->media];
            }

            public function save(MediaResource $mediaResource): void
            {
            }

            public function delete(MediaResource $mediaResource): void
            {
            }
        };

        $view = SessionSequenceView::fromDomain($sequence, mediaResourceRepository: $repository);

        self::assertSame('Partition actuelle', $view->documentMedia[0]->label);
        self::assertSame($catalogMedia->getUuid()->toRfc4122(), $view->storedMedia[0]->resourceUuid?->toRfc4122());

        $catalogMedia->setActive(false);
        $inactiveView = SessionSequenceView::fromDomain($sequence, mediaResourceRepository: $repository);
        self::assertSame([], $inactiveView->documentMedia);
        self::assertSame([], $inactiveView->storedMedia);
    }

    public function testRepertoireSourceExposesItsLinkedMediaInTheDocument(): void
    {
        $youtubeMedia = new MediaResource(
            type: MediaResourceType::VIDEO,
            title: 'Le réveil du dragon',
            primaryUrl: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        );
        $repertoireItem = new RepertoireItem(
            type: RepertoireItemType::NURSERY_RHYME,
            title: 'Le réveil du dragon',
            linkedMediaUuids: [$youtubeMedia->getUuid()->toRfc4122()],
        );
        $sessionSequence = new SessionSequence(
            uuid: Uuid::v4(),
            type: SessionSequenceType::NURSERY_RHYME,
            title: 'Le réveil du dragon',
            subtitle: null,
            body: '',
            lyrics: null,
            gestures: null,
            primaryUrl: null,
            secondaryUrl: null,
            imageUrl: null,
            showLyricsByDefault: false,
            sourceUuid: $repertoireItem->getUuid(),
            sourceKind: SessionSequenceSourceKind::REPERTOIRE_ITEM,
        );
        $mediaResourceRepository = new class($youtubeMedia) implements MediaResourceRepositoryInterface {
            public function __construct(private MediaResource $youtubeMedia)
            {
            }

            public function findByUuid(Uuid $uuid): ?MediaResource
            {
                return $this->youtubeMedia->getUuid()->equals($uuid) ? $this->youtubeMedia : null;
            }

            public function search(?string $query = null, ?MediaResourceType $mediaResourceType = null, bool $activeOnly = false): array
            {
                return [$this->youtubeMedia];
            }

            public function save(MediaResource $mediaResource): void
            {
            }

            public function delete(MediaResource $mediaResource): void
            {
            }
        };

        $sessionSequenceView = SessionSequenceView::fromDomain($sessionSequence, $repertoireItem, mediaResourceRepository: $mediaResourceRepository);

        self::assertCount(1, $sessionSequenceView->documentMedia);
        self::assertSame($youtubeMedia->getPrimaryUrl(), $sessionSequenceView->documentMedia[0]->url);
        self::assertTrue($sessionSequenceView->documentMedia[0]->featured);
        self::assertTrue($sessionSequenceView->hasRepertoireSource);

        $repertoireItem->setLinkedMediaVisibility($youtubeMedia->getUuid()->toRfc4122(), false);
        $hiddenSequenceView = SessionSequenceView::fromDomain($sessionSequence, $repertoireItem, mediaResourceRepository: $mediaResourceRepository);

        self::assertSame([], $hiddenSequenceView->documentMedia);
    }

    public function testRepertoireSourceSuppliesCurrentPublicContentWhileKeepingSessionInstructions(): void
    {
        $repertoireItem = new RepertoireItem(
            type: RepertoireItemType::NURSERY_RHYME,
            title: 'Au clair de la lune',
            source: 'Traditionnel',
            contentBlocks: [new RepertoireBlock(RepertoireBlockKind::LINE, 'Au clair de la lune', 'Avec les foulards')],
            generalInstructions: 'À reprendre en chœur.',
        );
        $sessionSequence = new SessionSequence(
            uuid: Uuid::v4(),
            type: SessionSequenceType::NURSERY_RHYME,
            title: 'Ancien titre',
            subtitle: null,
            body: 'Adapter le tempo au groupe.',
            lyrics: 'Anciennes paroles',
            gestures: null,
            primaryUrl: null,
            secondaryUrl: null,
            imageUrl: null,
            showLyricsByDefault: false,
            sourceUuid: $repertoireItem->getUuid(),
            sourceKind: SessionSequenceSourceKind::REPERTOIRE_ITEM,
            sourceTitle: 'Ancien titre',
        );

        $sessionSequenceView = SessionSequenceView::fromDomain($sessionSequence, $repertoireItem);

        self::assertSame('Au clair de la lune', $sessionSequenceView->title);
        self::assertSame('Traditionnel', $sessionSequenceView->subtitle);
        self::assertSame('À reprendre en chœur.', $sessionSequenceView->generalInstructions);
        self::assertSame('Au clair de la lune', $sessionSequenceView->lyrics);
        self::assertSame('Adapter le tempo au groupe.', $sessionSequenceView->body);
        self::assertCount(1, $sessionSequenceView->contentBlocks);
        self::assertSame(RepertoireBlockKind::LINE, $sessionSequenceView->contentBlocks[0]->kind);
        self::assertSame('Au clair de la lune', $sessionSequenceView->contentBlocks[0]->text);
        self::assertSame('Avec les foulards', $sessionSequenceView->contentBlocks[0]->gesture);
    }

    public function testItResolvesSequenceInstrumentsAndKeepsAllActivityMediaInTheComposer(): void
    {
        $instrument = new Instrument(name: 'Kalimba');
        $hiddenMedia = new SessionSequenceMedia(
            label: 'Support de préparation',
            type: MediaResourceType::LINK,
            url: 'https://example.test/preparation',
            imageUrl: null,
            featured: false,
            displayOnSession: false,
        );
        $visibleMedia = new SessionSequenceMedia(
            label: 'Écoute du jour',
            type: MediaResourceType::SOUNDTRACK,
            url: 'https://example.test/ecoute',
            imageUrl: null,
            featured: false,
            displayOnSession: true,
        );
        $activity = new SessionSequence(
            uuid: Uuid::v4(),
            type: SessionSequenceType::FREE,
            title: 'Jeu de rythmes',
            subtitle: null,
            body: '',
            lyrics: null,
            gestures: null,
            primaryUrl: null,
            secondaryUrl: null,
            imageUrl: null,
            showLyricsByDefault: false,
            instrumentUuids: [$instrument->getUuid()->toRfc4122()],
            media: [$hiddenMedia, $visibleMedia],
        );
        $repertoireSequence = new SessionSequence(
            uuid: Uuid::v4(),
            type: SessionSequenceType::NURSERY_RHYME,
            title: 'Comptine',
            subtitle: null,
            body: '',
            lyrics: null,
            gestures: null,
            primaryUrl: null,
            secondaryUrl: null,
            imageUrl: null,
            showLyricsByDefault: false,
            sourceUuid: Uuid::v4(),
            sourceKind: SessionSequenceSourceKind::REPERTOIRE_ITEM,
            instrumentUuids: [$instrument->getUuid()->toRfc4122()],
            media: [$hiddenMedia, $visibleMedia],
        );
        $instrumentRepository = new class($instrument) implements InstrumentRepositoryInterface {
            public function __construct(private Instrument $instrument)
            {
            }

            public function findByUuid(Uuid $uuid): ?Instrument
            {
                return $this->instrument->getUuid()->equals($uuid) ? $this->instrument : null;
            }

            public function findAllOrderedByName(): array
            {
                return [$this->instrument];
            }

            public function save(Instrument $instrument): void
            {
            }

            public function delete(Instrument $instrument): void
            {
            }
        };

        $activityView = SessionSequenceView::fromDomain($activity, instrumentRepository: $instrumentRepository);
        $repertoireView = SessionSequenceView::fromDomain($repertoireSequence, instrumentRepository: $instrumentRepository);

        self::assertSame(['Kalimba'], $activityView->instrumentNames);
        self::assertSame([$hiddenMedia, $visibleMedia], $activityView->composerMedia);
        self::assertSame([$visibleMedia], $repertoireView->composerMedia);
    }
}
