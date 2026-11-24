<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\ComposeFamilySession;
use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Model\Session\SessionFamily;
use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\SessionFamilyRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ComposeFamilySessionTest extends TestCase
{
    public function testSelectionOrderAndIndependentCopies(): void
    {
        $family = new SessionFamily('Forêt');
        $first = SessionSequence::fromArray(['type' => 'free', 'title' => 'Premier', 'body' => 'Jeu']);
        $second = SessionSequence::fromArray(['type' => 'movement', 'title' => 'Deuxième', 'body' => 'Danse']);
        $source = new SessionSummary('Source', new DateTimeImmutable('2026-11-01'), sequences: [$first, $second], familyUuid: $family->uuid, published: true);
        $families = $this->createMock(SessionFamilyRepositoryInterface::class);
        $families->method('findByUuid')->willReturn($family);
        $families->method('sessions')->willReturn([$source]);
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->expects(self::once())->method('save');
        $composer = new ComposeFamilySession($families, $sessions, $this->createMock(RepertoireItemRepositoryInterface::class));
        $selected = [
            ['session' => $source->getUuid()->toRfc4122(), 'sequence' => $second->uuid->toRfc4122(), 'instructions' => false],
            ['session' => $source->getUuid()->toRfc4122(), 'sequence' => $first->uuid->toRfc4122(), 'instructions' => false],
        ];
        $copy = $composer($family->uuid, 'Nouvelle', new DateTimeImmutable('2026-12-01'), $selected);
        self::assertSame(['Deuxième', 'Premier'], array_map(static fn ($sequence): string => $sequence->title, $copy->getSequences()));
        self::assertFalse($copy->isPublished());
        self::assertSame([], $copy->getOrganizations());
        self::assertTrue($family->uuid->equals($copy->getFamilyUuid()));
        self::assertTrue($second->uuid->equals($copy->getSequences()[0]->originUuid));
        self::assertFalse($second->uuid->equals($copy->getSequences()[0]->uuid));
    }

    public function testRepertoireSelectionCanIncludeOrOmitLocalInstructions(): void
    {
        $family = new SessionFamily('Forêt');
        $repertoireItem = new RepertoireItem(RepertoireItemType::NURSERY_RHYME, 'Comptine');
        $sequence = SessionSequence::fromArray(['type' => 'nursery_rhyme', 'title' => 'Comptine', 'body' => 'Chanter doucement', 'role' => 'Accueil', 'sourceKind' => 'repertoire_item', 'sourceUuid' => $repertoireItem->getUuid()->toRfc4122()]);
        $source = new SessionSummary('Source', new DateTimeImmutable('2026-11-01'), sequences: [$sequence], familyUuid: $family->uuid);
        $families = $this->createMock(SessionFamilyRepositoryInterface::class);
        $families->method('findByUuid')->willReturn($family);
        $families->method('sessions')->willReturn([$source]);
        $repertoire = $this->createMock(RepertoireItemRepositoryInterface::class);
        $repertoire->method('findByUuid')->willReturn($repertoireItem);
        $composer = new ComposeFamilySession($families, $this->createMock(SessionSummaryRepositoryInterface::class), $repertoire);
        $selection = ['session' => $source->getUuid()->toRfc4122(), 'sequence' => $sequence->uuid->toRfc4122(), 'instructions' => false];
        $general = $composer($family->uuid, 'Générale', new DateTimeImmutable('2026-12-01'), [$selection])->getSequences()[0];
        self::assertSame('', $general->body);
        self::assertNull($general->role);
        self::assertTrue($repertoireItem->getUuid()->equals($general->sourceUuid));
        $selection['instructions'] = true;
        $adapted = $composer($family->uuid, 'Adaptée', new DateTimeImmutable('2026-12-01'), [$selection])->getSequences()[0];
        self::assertSame('Chanter doucement', $adapted->body);
        self::assertSame('Accueil', $adapted->role);
        self::assertTrue($repertoireItem->getUuid()->equals($adapted->sourceUuid));
    }

    public function testRejectsTwoVersionsOfSameFreeElement(): void
    {
        $family = new SessionFamily('Forêt');
        $first = SessionSequence::fromArray(['type' => 'free', 'title' => 'Jeu', 'body' => 'Un']);
        $secondPayload = $first->toArray();
        $secondPayload['originUuid'] = $first->uuid->toRfc4122();
        $secondPayload['uuid'] = Uuid::v4()->toRfc4122();
        $second = SessionSequence::fromArray($secondPayload);
        $source = new SessionSummary('Source', new DateTimeImmutable('2026-11-01'), sequences: [$first, $second], familyUuid: $family->uuid);
        $families = $this->createMock(SessionFamilyRepositoryInterface::class);
        $families->method('findByUuid')->willReturn($family);
        $families->method('sessions')->willReturn([$source]);
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->expects(self::never())->method('save');
        $composer = new ComposeFamilySession($families, $sessions, $this->createMock(RepertoireItemRepositoryInterface::class));
        $sourceUuid = $source->getUuid()->toRfc4122();
        $this->expectException(InvalidArgumentException::class);
        $composer($family->uuid, 'Copie', new DateTimeImmutable('2026-12-01'), [
            ['session' => $sourceUuid, 'sequence' => $first->uuid->toRfc4122(), 'instructions' => false],
            ['session' => $sourceUuid, 'sequence' => $second->uuid->toRfc4122(), 'instructions' => false],
        ]);
    }

    public function testRejectsAnElementOutsideTheFamily(): void
    {
        $family = new SessionFamily('Forêt');
        $families = $this->createMock(SessionFamilyRepositoryInterface::class);
        $families->method('findByUuid')->willReturn($family);
        $families->method('sessions')->willReturn([]);
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->expects(self::never())->method('save');
        $composer = new ComposeFamilySession($families, $sessions, $this->createMock(RepertoireItemRepositoryInterface::class));
        $this->expectException(InvalidArgumentException::class);
        $composer($family->uuid, 'Copie', new DateTimeImmutable('2026-12-01'), [['session' => Uuid::v4()->toRfc4122(), 'sequence' => Uuid::v4()->toRfc4122(), 'instructions' => false]]);
    }

    public function testRejectsARepertoireElementThatNoLongerExists(): void
    {
        $family = new SessionFamily('Forêt');
        $sequence = SessionSequence::fromArray(['type' => 'nursery_rhyme', 'title' => 'Comptine', 'body' => '', 'sourceKind' => 'repertoire_item', 'sourceUuid' => Uuid::v4()->toRfc4122()]);
        $source = new SessionSummary('Source', new DateTimeImmutable('2026-11-01'), sequences: [$sequence], familyUuid: $family->uuid);
        $families = $this->createMock(SessionFamilyRepositoryInterface::class);
        $families->method('findByUuid')->willReturn($family);
        $families->method('sessions')->willReturn([$source]);
        $sessions = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessions->expects(self::never())->method('save');
        $repertoire = $this->createMock(RepertoireItemRepositoryInterface::class);
        $repertoire->method('findByUuid')->willReturn(null);
        $this->expectException(InvalidArgumentException::class);
        (new ComposeFamilySession($families, $sessions, $repertoire))($family->uuid, 'Copie', new DateTimeImmutable('2026-12-01'), [['session' => $source->getUuid()->toRfc4122(), 'sequence' => $sequence->uuid->toRfc4122(), 'instructions' => false]]);
    }
}
