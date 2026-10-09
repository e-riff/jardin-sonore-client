<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Commercial;

use App\Application\Commercial\CommercialProjectWorkflow;
use App\Application\Commercial\RecordCommercialQuote;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CommercialQuoteWorkflowTest extends TestCase
{
    public function testSentQuoteIsImmutableAndSignatureConfirmsProject(): void
    {
        $clock = new MockClock('2026-10-09 08:00:00');
        $projectEntity = new CommercialProjectEntity('Année scolaire', new OrganizationEntity(), null, new DateTimeImmutable());
        $workflow = new RecordCommercialQuote(new CommercialProjectWorkflow($clock), $clock);

        $firstQuoteEntity = $workflow->sent($projectEntity, '2026-10 - d1', '2026-10 - d1 - Mairie', 31000, new DateTimeImmutable('2026-10-09'));
        $secondQuoteEntity = $workflow->sent($projectEntity, '2026-10 - d2', '2026-10 - d2 - Mairie', 12000, new DateTimeImmutable('2026-10-09'), $firstQuoteEntity);
        self::assertSame($firstQuoteEntity, $secondQuoteEntity->getReplaces());
        self::assertSame(31000, $firstQuoteEntity->getAmountCents());
        self::assertSame(CommercialProjectEntity::STATUS_DISCUSSION, $projectEntity->getStatus());

        $workflow->markSigned($firstQuoteEntity, new DateTimeImmutable('2026-10-10'));
        self::assertSame(CommercialProjectEntity::STATUS_CONFIRMED, $projectEntity->getStatus());
        self::assertNull($secondQuoteEntity->getSignedOn());
        self::assertCount(4, $projectEntity->getEvents());
    }
}
