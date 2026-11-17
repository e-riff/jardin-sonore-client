<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\DuplicateSessionSummary;
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
