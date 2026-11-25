<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Session;

use App\Domain\Model\Session\SessionDocumentStatus;
use App\Domain\Model\Session\SessionSummary;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionSummaryDocumentTest extends TestCase
{
    #[Test]
    public function aNewSessionIsUnpublishedAndChangingPublicationKeepsItsPdfReady(): void
    {
        $sessionSummary = new SessionSummary(title: 'Matin musical', sessionDate: new DateTimeImmutable('2026-07-30'));
        self::assertFalse($sessionSummary->isPublished());
        $sessionSummary->markDocumentReady('/tmp/matin-musical.pdf');

        $sessionSummary->setPublished(true);

        self::assertTrue($sessionSummary->isPublished());
        self::assertSame(SessionDocumentStatus::READY, $sessionSummary->getDocumentStatus());
        self::assertSame('/tmp/matin-musical.pdf', $sessionSummary->getDocumentPath());
    }

    #[Test]
    public function rehydrationPreservesReadyDocumentAndUpdateDate(): void
    {
        $updatedAt = new DateTimeImmutable('2026-07-01 12:00:00');
        $session = new SessionSummary(title: 'Séance', sessionDate: new DateTimeImmutable('2026-07-30'), updatedAt: $updatedAt, documentStatus: SessionDocumentStatus::READY, documentPath: '/tmp/session.pdf');
        self::assertSame(SessionDocumentStatus::READY, $session->getDocumentStatus());
        self::assertSame('/tmp/session.pdf', $session->getDocumentPath());
        self::assertSame($updatedAt, $session->getUpdatedAt());
    }

    #[Test]
    public function itMarksItsDocumentAsPendingWhenDetailsChange(): void
    {
        $sessionSummary = new SessionSummary(
            title: 'Matin musical',
            sessionDate: new DateTimeImmutable('2026-07-30'),
            organizations: [],
        );
        $sessionSummary->markDocumentReady('/tmp/matin-musical.pdf');

        $sessionSummary->updateDetails(
            title: 'Matin musical',
            sessionDate: new DateTimeImmutable('2026-07-30'),
            organizations: [],
            theme: null,
            generalNotes: null,
            materialSummary: null,
            furtherExploration: null,
            instrumentUuids: [],
        );

        self::assertSame(SessionDocumentStatus::PENDING, $sessionSummary->getDocumentStatus());
        self::assertNull($sessionSummary->getDocumentPath());
    }
}
