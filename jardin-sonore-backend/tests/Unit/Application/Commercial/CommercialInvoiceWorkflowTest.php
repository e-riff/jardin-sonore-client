<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Commercial;

use App\Application\Commercial\CommercialActionWorkflow;
use App\Application\Commercial\CommercialProjectWorkflow;
use App\Application\Commercial\RecordCommercialInvoice;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CommercialInvoiceWorkflowTest extends TestCase
{
    public function testOverdueInvoiceCreatesOneActionAndPaymentAllowsClosing(): void
    {
        $clock = new MockClock('2026-10-09 08:00:00');
        $projectEntity = new CommercialProjectEntity('Ateliers', new OrganizationEntity(), null, new DateTimeImmutable());
        $projectWorkflow = new CommercialProjectWorkflow($clock);
        $projectWorkflow->confirm($projectEntity);
        $recorder = new RecordCommercialInvoice(new CommercialActionWorkflow($clock), $clock);

        $invoiceEntity = $recorder->issue($projectEntity, '2026-09 - 001', 31000, new DateTimeImmutable('2026-09-01'));
        self::assertCount(1, $projectEntity->getActions());
        self::assertSame(CommercialActionEntity::STATUS_OPEN, $invoiceEntity->getReminderAction()?->getStatus());
        $this->expectException(DomainException::class);
        $projectWorkflow->complete($projectEntity);
    }

    public function testMarkPaidCancelsOpenReminderAndPreservesInvoice(): void
    {
        $clock = new MockClock('2026-10-09 08:00:00');
        $projectEntity = new CommercialProjectEntity('Ateliers', new OrganizationEntity(), null, new DateTimeImmutable());
        $projectWorkflow = new CommercialProjectWorkflow($clock);
        $projectWorkflow->confirm($projectEntity);
        $recorder = new RecordCommercialInvoice(new CommercialActionWorkflow($clock), $clock);
        $invoiceEntity = $recorder->issue($projectEntity, '2026-09 - 001', 31000, new DateTimeImmutable('2026-09-01'));

        $recorder->markPaid($invoiceEntity, new DateTimeImmutable('2026-10-09'));
        self::assertSame(CommercialActionEntity::STATUS_CANCELED, $invoiceEntity->getReminderAction()?->getStatus());
        self::assertSame('2026-10-09', $invoiceEntity->getPaidOn()?->format('Y-m-d'));
        $projectWorkflow->complete($projectEntity);
        self::assertSame(CommercialProjectEntity::STATUS_COMPLETED, $projectEntity->getStatus());
    }
}
