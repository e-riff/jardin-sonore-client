<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\DeleteRepertoireItem;
use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSequenceSourceKind;
use App\Domain\Model\Session\SessionSequenceType;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DeleteRepertoireItemTest extends TestCase
{
    public function testItRefusesToDeleteAnItemUsedInASession(): void
    {
        $repertoireItem = new RepertoireItem(RepertoireItemType::NURSERY_RHYME, 'Farine et Jasmin');
        $sessionSummary = new SessionSummary(
            title: 'Brésil !',
            sessionDate: new DateTimeImmutable('2026-07-30'),
            organizations: [],
            sequences: [new SessionSequence(
                uuid: Uuid::v7(),
                type: SessionSequenceType::NURSERY_RHYME,
                title: 'Farine et Jasmin',
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
            )],
            uuid: Uuid::v7(),
        );
        $repertoireItemRepository = new class($repertoireItem) implements RepertoireItemRepositoryInterface {
            public bool $deleted = false;

            public function __construct(private readonly RepertoireItem $repertoireItem)
            {
            }

            public function findByUuid(Uuid $uuid): ?RepertoireItem
            {
                return $uuid->equals($this->repertoireItem->getUuid()) ? $this->repertoireItem : null;
            }

            public function search(?RepertoireItemType $repertoireItemType = null, ?string $query = null, bool $activeOnly = false): array
            {
                return [$this->repertoireItem];
            }

            public function save(RepertoireItem $repertoireItem): void
            {
            }

            public function delete(RepertoireItem $repertoireItem): void
            {
                $this->deleted = true;
            }
        };
        $sessionSummaryRepository = new class($sessionSummary) implements SessionSummaryRepositoryInterface {
            public function __construct(private readonly SessionSummary $sessionSummary)
            {
            }

            public function findByUuid(Uuid $uuid): ?SessionSummary
            {
                return null;
            }

            public function search(?string $query = null): array
            {
                return [$this->sessionSummary];
            }

            public function save(SessionSummary $sessionSummary, bool $scheduleDocumentGeneration = true): void
            {
            }

            public function remove(SessionSummary $sessionSummary): void
            {
            }
        };

        $deleteRepertoireItem = new DeleteRepertoireItem($repertoireItemRepository, $sessionSummaryRepository);

        $this->expectException(LogicException::class);
        $deleteRepertoireItem($repertoireItem->getUuid());
        self::assertFalse($repertoireItemRepository->deleted);
    }
}
