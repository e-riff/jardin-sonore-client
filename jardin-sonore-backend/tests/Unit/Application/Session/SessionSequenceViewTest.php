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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SessionSequenceViewTest extends TestCase
{
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
            notes: null,
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
            notes: null,
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
            notes: null,
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
            notes: null,
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
