<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\DuplicateSessionSummary;
use App\Application\Session\SaveSessionSequenceInput;
use App\Application\Session\UpdateSessionSequence;
use App\Application\Session\UpdateSessionSequenceRole;
use App\Domain\Model\AddressBook\Organization;
use App\Domain\Model\ContentCatalog\Theme;
use App\Domain\Model\Session\SessionDocumentStatus;
use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DuplicateSessionSummaryTest extends TestCase
{
    public function testFreeSequenceCopiesKeepStableOriginAndDistinctIdentities(): void
    {
        $sequence = SessionSequence::fromArray(['type' => 'free', 'title' => 'Jeu libre', 'body' => 'Première version']);
        self::assertNull($sequence->originUuid);
        $source = new SessionSummary(title: 'Forêt', sessionDate: new DateTimeImmutable('2026-11-24'), sequences: [$sequence]);
        $repository = $this->createMock(SessionSummaryRepositoryInterface::class);
        $repository->method('findByUuid')->willReturnOnConsecutiveCalls($source, null);
        $firstCopy = (new DuplicateSessionSummary($repository))($source->getUuid(), 'Copie');
        self::assertNotNull($firstCopy);
        $firstSequence = $firstCopy->getSequences()[0];
        self::assertTrue($sequence->uuid->equals($firstSequence->originUuid));
        self::assertFalse($sequence->uuid->equals($firstSequence->uuid));
        $roundTrip = SessionSequence::fromArray($firstSequence->toArray());
        self::assertTrue($sequence->uuid->equals($roundTrip->originUuid));
        $copyRepository = $this->createMock(SessionSummaryRepositoryInterface::class);
        $copyRepository->method('findByUuid')->willReturn($firstCopy);
        $secondCopy = (new DuplicateSessionSummary($copyRepository))($firstCopy->getUuid(), 'Autre copie');
        self::assertNotNull($secondCopy);
        self::assertTrue($sequence->uuid->equals($secondCopy->getSequences()[0]->originUuid));
        self::assertFalse($firstSequence->uuid->equals($secondCopy->getSequences()[0]->uuid));
    }

    public function testDuplicateKeepsFamily(): void
    {
        $familyUuid = Uuid::v4();
        $source = new SessionSummary(title: 'Forêt', sessionDate: new DateTimeImmutable('2026-11-24'), familyUuid: $familyUuid);
        $repository = $this->createMock(SessionSummaryRepositoryInterface::class);
        $repository->method('findByUuid')->willReturn($source);
        $copy = (new DuplicateSessionSummary($repository))($source->getUuid(), 'Forêt copie');
        self::assertNotNull($copy);
        self::assertTrue($familyUuid->equals($copy->getFamilyUuid()));
        self::assertFalse($copy->isPublished());
    }

    public function testEditingCopiedSequenceKeepsItsOriginAndMedia(): void
    {
        $origin = Uuid::v4();
        $sequence = SessionSequence::fromArray(['type' => 'free', 'title' => 'Jeu', 'body' => 'Ancien', 'originUuid' => $origin->toRfc4122(), 'media' => [['label' => 'Musique', 'type' => 'link', 'url' => 'https://example.test/music', 'featured' => true, 'displayOnSession' => true]]]);
        $session = new SessionSummary('Séance', new DateTimeImmutable('2026-11-24'), sequences: [$sequence]);
        $repository = $this->createMock(SessionSummaryRepositoryInterface::class);
        $repository->method('findByUuid')->willReturn($session);
        $input = new SaveSessionSequenceInput($sequence->type, 'Jeu modifié', null, 'Nouveau', null, null, null, null, null, false, null, null, null, null, [], $sequence->getMedia());
        (new UpdateSessionSequence($repository))($session->getUuid(), $sequence->uuid, $input);
        (new UpdateSessionSequenceRole($repository))($session->getUuid(), $sequence->uuid, 'Accueil');
        $saved = $session->getSequences()[0];
        self::assertTrue($origin->equals($saved->originUuid));
        self::assertSame('Nouveau', $saved->body);
        self::assertCount(1, $saved->getMedia());
        self::assertSame('https://example.test/music', $saved->getMedia()[0]->url);
    }

    public function testBlankOnlyLyricLinesSurviveJsonRoundTrip(): void
    {
        $sequence = SessionSequence::fromArray(['type' => 'free', 'title' => 'Gestes', 'body' => '', 'lyrics' => "\n\n", 'gestures' => "Lever\nBaisser\n"]);
        self::assertSame("\n\n", $sequence->lyrics);
        self::assertSame("\n\n", SessionSequence::fromArray($sequence->toArray())->lyrics);
    }

    public function testCopiesContentWithNewIdentitiesWithoutPublicationOrOrganizations(): void
    {
        $sequence = SessionSequence::fromArray(['type' => 'nursery_rhyme', 'title' => 'Chanson', 'body' => 'Paroles', 'lyrics' => 'La la', 'gestures' => 'Taper', 'primaryUrl' => 'https://example.test/chanson', 'sourceUuid' => Uuid::v4()->toRfc4122(), 'sourceKind' => 'repertoire_item', 'role' => 'Accueil', 'showLyricsByDefault' => true]);
        $source = new SessionSummary(title: 'Printemps', sessionDate: new DateTimeImmutable('2026-03-01'),
            organizations: [new Organization('Crèche')], generalNotes: 'Notes', materialSummary: 'Tambour',
            furtherExploration: 'Suite', instrumentUuids: [Uuid::v4()->toRfc4122()], recommendationUuids: [Uuid::v4()->toRfc4122()], sequences: [$sequence], themes: [new Theme('Printemps')], published: true,
            firstPublishedAt: new DateTimeImmutable('2026-03-01'));
        $source->markDocumentReady('old.pdf');
        $repository = $this->createMock(SessionSummaryRepositoryInterface::class);
        $repository->method('findByUuid')->willReturn($source);
        $repository->expects(self::once())->method('save')->with(self::callback(static function (SessionSummary $copy) use ($source, $sequence): bool {
            self::assertFalse($copy->getUuid()->equals($source->getUuid()));
            self::assertSame('Printemps (copie)', $copy->getTitle());
            self::assertFalse($copy->isPublished());
            self::assertNull($copy->getFirstPublishedAt());
            self::assertSame([], $copy->getOrganizations());
            self::assertNull($copy->getDocumentPath());
            self::assertSame(SessionDocumentStatus::PENDING, $copy->getDocumentStatus());
            self::assertSame($source->getGeneralNotes(), $copy->getGeneralNotes());
            self::assertSame($source->getMaterialSummary(), $copy->getMaterialSummary());
            self::assertSame($source->getFurtherExploration(), $copy->getFurtherExploration());
            self::assertSame($source->getInstrumentUuids(), $copy->getInstrumentUuids());
            self::assertSame($source->getRecommendationUuids(), $copy->getRecommendationUuids());
            self::assertSame($source->getThemes(), $copy->getThemes());
            self::assertCount(1, $copy->getSequences());
            $sourcePayload = $sequence->toArray();
            $copyPayload = $copy->getSequences()[0]->toArray();
            unset($sourcePayload['uuid'], $copyPayload['uuid']);
            self::assertSame($sourcePayload, $copyPayload);
            self::assertFalse($copy->getSequences()[0]->uuid->equals($sequence->uuid));
            self::assertSame($sequence->body, $copy->getSequences()[0]->body);

            return true;
        }));
        $copy = (new DuplicateSessionSummary($repository))($source->getUuid(), 'Printemps (copie)');
        self::assertNotNull($copy);
        self::assertTrue($source->isPublished());
        self::assertCount(1, $source->getOrganizations());
    }
}
