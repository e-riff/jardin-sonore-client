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
