<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\McpSessionWriter;
use App\Application\Storage\MediaResourceFileStorageInterface;
use App\Domain\Model\ContentCatalog\Instrument;
use App\Domain\Model\Session\MediaResource;
use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Model\Session\SessionFamily;
use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSequenceType;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\SessionFamilyRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class McpSessionWriterTest extends TestCase
{
    public function testPublishedSessionsCannotBeModified(): void
    {
        $session = new SessionSummary('Séance publiée', new DateTimeImmutable('2026-11-25'), published: true);
        $sessions = $this->createStub(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);

        $this->expectException(InvalidArgumentException::class);
        $writer->updateSession($session->getUuid()->toRfc4122(), 'Nouvelle séance', '2026-11-26', str_repeat('a', 64));
    }

    public function testEditingDraftPreservesRelatedData(): void
    {
        $session = new SessionSummary('Ancien titre', new DateTimeImmutable('2026-11-25'), recommendationUuids: ['019f84ff-79a6-7947-856f-cdb5f2d26a2b'], instrumentUuids: ['019f84ff-79a6-7947-856f-cdb5f2d26a2b']);
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $sessions->expects(self::once())->method('save')->with($session);
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);

        $writer->updateSession($session->getUuid()->toRfc4122(), 'Nouveau titre', '2026-12-01', McpSessionWriter::revision($session));

        self::assertSame('Nouveau titre', $session->getTitle());
        self::assertSame(['019f84ff-79a6-7947-856f-cdb5f2d26a2b'], $session->getRecommendationUuids());
        self::assertSame(['019f84ff-79a6-7947-856f-cdb5f2d26a2b'], $session->getInstrumentUuids());
    }

    public function testRejectsStaleRevision(): void
    {
        $session = new SessionSummary('Titre actuel', new DateTimeImmutable('2026-11-25'));
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $sessions->expects(self::never())->method('save');
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);

        $this->expectException(InvalidArgumentException::class);
        $writer->updateSession($session->getUuid()->toRfc4122(), 'Autre titre', '2026-11-26', str_repeat('a', 64));
    }

    public function testCreatesAnUnpublishedSessionWithoutEstablishments(): void
    {
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn(null);
        $sessions->expects(self::once())->method('save')->with(self::callback(static fn (SessionSummary $session): bool => !$session->isPublished() && [] === $session->getOrganizations() && 'Nouvelle séance' === $session->getTitle()));
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);

        $writer->createSession('request-unique', 'Nouvelle séance', '2026-12-01');
    }

    public function testRejectsReusedRequestIdWhenSessionHasDifferentContent(): void
    {
        $existing = new SessionSummary('Autre séance', new DateTimeImmutable('2026-12-01'));
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($existing);
        $sessions->expects(self::never())->method('save');
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);

        $this->expectException(InvalidArgumentException::class);
        $writer->createSession('request-unique', 'Nouvelle séance', '2026-12-01');
    }

    public function testCreatesFamilyWithStableIdentity(): void
    {
        $families = $this->createMock(SessionFamilyRepositoryInterface::class);
        $families->method('findByUuid')->willReturn(null);
        $families->expects(self::once())->method('save')->with(self::callback(static fn (SessionFamily $family): bool => 'Forêt' === $family->name));
        $writer = new McpSessionWriter($families, $this->createStub(SessionSummaryRepositoryInterface::class));
        $writer->createFamily('family-unique', 'Forêt', []);
    }

    public function testCannotMovePublishedSessionBetweenFamilies(): void
    {
        $session = new SessionSummary('Publiée', new DateTimeImmutable('2026-11-25'), published: true);
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $sessions->expects(self::never())->method('save');
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);

        $this->expectException(InvalidArgumentException::class);
        $writer->assignFamily($session->getUuid()->toRfc4122(), null, McpSessionWriter::revision($session));
    }

    public function testAddsFreeElementToDraft(): void
    {
        $session = new SessionSummary('Brouillon', new DateTimeImmutable('2026-11-25'));
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $sessions->expects(self::once())->method('save');
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);
        $writer->addFreeElement($session->getUuid()->toRfc4122(), McpSessionWriter::revision($session), 'Le jeu', 'free', 'Explorer les sons');
        self::assertSame('Le jeu', $session->getSequences()[0]->title);
        self::assertSame('Explorer les sons', $session->getSequences()[0]->body);
    }

    public function testCopiesElementWithoutChangingPublishedSource(): void
    {
        $sequence = new SessionSequence(Uuid::v4(), SessionSequenceType::FREE, 'Jeu', null, 'Consigne', null, null, null, null, null, false);
        $source = new SessionSummary('Source', new DateTimeImmutable('2026-11-25'), sequences: [$sequence], published: true);
        $target = new SessionSummary('Brouillon', new DateTimeImmutable('2026-11-26'));
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturnCallback(static fn (Uuid $uuid): SessionSummary => $uuid->equals($source->getUuid()) ? $source : $target);
        $sessions->expects(self::once())->method('save')->with($target);
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);

        $copy = $writer->copyElement($target->getUuid()->toRfc4122(), McpSessionWriter::revision($target), $source->getUuid()->toRfc4122(), $sequence->uuid->toRfc4122(), McpSessionWriter::revision($source));
        self::assertFalse($copy->uuid->equals($sequence->uuid));
        self::assertSame('Consigne', $copy->body);
        self::assertCount(1, $source->getSequences());
    }

    public function testUpdatesElementWithoutDiscardingLyrics(): void
    {
        $sequence = new SessionSequence(Uuid::v4(), SessionSequenceType::FREE, 'Jeu', null, 'Ancien', 'Paroles', null, null, null, null, false);
        $session = new SessionSummary('Brouillon', new DateTimeImmutable('2026-11-25'), sequences: [$sequence]);
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $sessions->expects(self::once())->method('save');
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);
        $writer->updateElement($session->getUuid()->toRfc4122(), McpSessionWriter::revision($session), $sequence->uuid->toRfc4122(), ['body' => 'Nouveau']);
        self::assertSame('Nouveau', $session->getSequences()[0]->body);
        self::assertSame('Paroles', $session->getSequences()[0]->lyrics);
    }

    public function testAddsExistingRepertoireItemAsLinkedElement(): void
    {
        $session = new SessionSummary('Brouillon', new DateTimeImmutable('2026-11-25'));
        $item = new RepertoireItem(RepertoireItemType::NURSERY_RHYME, 'Comptine');
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $sessions->expects(self::once())->method('save');
        $repertoire = $this->createStub(RepertoireItemRepositoryInterface::class);
        $repertoire->method('findByUuid')->willReturn($item);
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions, null, $repertoire);
        $sequence = $writer->addRepertoireElement($session->getUuid()->toRfc4122(), McpSessionWriter::revision($session), $item->getUuid()->toRfc4122());
        self::assertSame($item->getUuid()->toRfc4122(), $sequence->sourceUuid?->toRfc4122());
    }

    public function testAttachesExistingMediaToElement(): void
    {
        $sequence = new SessionSequence(Uuid::v4(), SessionSequenceType::FREE, 'Jeu', null, '', null, null, null, null, null, false);
        $session = new SessionSummary('Brouillon', new DateTimeImmutable('2026-11-25'), sequences: [$sequence]);
        $media = new MediaResource(MediaResourceType::VIDEO, 'Vidéo', 'https://youtube.com/watch?v=example');
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $sessions->expects(self::once())->method('save');
        $mediaRepository = $this->createStub(MediaResourceRepositoryInterface::class);
        $mediaRepository->method('findByUuid')->willReturn($media);
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions, null, null, $mediaRepository);
        $writer->attachMedia($session->getUuid()->toRfc4122(), McpSessionWriter::revision($session), $sequence->uuid->toRfc4122(), $media->getUuid()->toRfc4122());
        self::assertSame($media->getUuid()->toRfc4122(), $session->getSequences()[0]->getMedia()[0]->resourceUuid?->toRfc4122());
    }

    public function testReordersOnlyTheExactExistingElements(): void
    {
        $first = new SessionSequence(Uuid::v4(), SessionSequenceType::FREE, 'Un', null, '', null, null, null, null, null, false);
        $second = new SessionSequence(Uuid::v4(), SessionSequenceType::FREE, 'Deux', null, '', null, null, null, null, null, false);
        $session = new SessionSummary('Brouillon', new DateTimeImmutable('2026-11-25'), sequences: [$first, $second]);
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $sessions->expects(self::once())->method('save');
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);
        $writer->reorderElements($session->getUuid()->toRfc4122(), McpSessionWriter::revision($session), [$second->uuid->toRfc4122(), $first->uuid->toRfc4122()]);
        self::assertSame('Deux', $session->getSequences()[0]->title);
    }

    public function testRejectsUnexpectedFileContentBeforeStorage(): void
    {
        $storage = $this->createMock(MediaResourceFileStorageInterface::class);
        $storage->expects(self::never())->method('storePrimaryFile');
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $this->createStub(SessionSummaryRepositoryInterface::class), null, null, $this->createStub(MediaResourceRepositoryInterface::class), $storage);
        $this->expectException(InvalidArgumentException::class);
        $writer->createFileMedia('req-file', 'Document', 'link', 'document.pdf', base64_encode('not a pdf'));
    }

    public function testCreatesPdfMediaFromValidatedFile(): void
    {
        $storage = $this->createMock(MediaResourceFileStorageInterface::class);
        $storage->expects(self::once())->method('storePrimaryFile')->willReturn('uploads/media/resources/test.pdf');
        $mediaRepository = $this->createMock(MediaResourceRepositoryInterface::class);
        $mediaRepository->method('findByUuid')->willReturn(null);
        $mediaRepository->expects(self::once())->method('save');
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $this->createStub(SessionSummaryRepositoryInterface::class), null, null, $mediaRepository, $storage);
        $media = $writer->createFileMedia('req-file', 'Partition', 'score', 'partition.pdf', base64_encode("%PDF-1.4\n%%EOF\n"));
        self::assertSame('uploads/media/resources/test.pdf', $media->getPrimaryUrl());
    }

    public function testAttachesAnExistingInstrumentToElement(): void
    {
        $sequence = new SessionSequence(Uuid::v4(), SessionSequenceType::FREE, 'Jeu', null, '', null, null, null, null, null, false);
        $session = new SessionSummary('Brouillon', new DateTimeImmutable('2026-11-25'), sequences: [$sequence]);
        $instrument = new Instrument('Tambour');
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $sessions->expects(self::once())->method('save');
        $instruments = $this->createStub(InstrumentRepositoryInterface::class);
        $instruments->method('findByUuid')->willReturn($instrument);
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions, null, null, null, null, $instruments);
        $writer->attachInstrument($session->getUuid()->toRfc4122(), McpSessionWriter::revision($session), $sequence->uuid->toRfc4122(), $instrument->getUuid()->toRfc4122());
        self::assertContains($instrument->getUuid()->toRfc4122(), $session->getSequences()[0]->instrumentUuids);
    }

    public function testUpdatesSessionNotesWithoutChangingTitle(): void
    {
        $session = new SessionSummary('Brouillon', new DateTimeImmutable('2026-11-25'));
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $sessions->expects(self::once())->method('save');
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);
        $writer->updateSessionContent($session->getUuid()->toRfc4122(), McpSessionWriter::revision($session), ['generalNotes' => 'À préparer']);
        self::assertSame('À préparer', $session->getGeneralNotes());
        self::assertSame('Brouillon', $session->getTitle());
    }

    public function testRemovingElementDoesNotAffectAnotherElement(): void
    {
        $first = new SessionSequence(Uuid::v4(), SessionSequenceType::FREE, 'Un', null, '', null, null, null, null, null, false);
        $second = new SessionSequence(Uuid::v4(), SessionSequenceType::FREE, 'Deux', null, '', null, null, null, null, null, false);
        $session = new SessionSummary('Brouillon', new DateTimeImmutable('2026-11-25'), sequences: [$first, $second]);
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->method('findByUuid')->willReturn($session);
        $sessions->expects(self::once())->method('save');
        $writer = new McpSessionWriter($this->createStub(SessionFamilyRepositoryInterface::class), $sessions);
        $writer->removeElement($session->getUuid()->toRfc4122(), McpSessionWriter::revision($session), $first->uuid->toRfc4122());
        self::assertCount(1, $session->getSequences());
        self::assertSame('Deux', $session->getSequences()[0]->title);
    }
}
