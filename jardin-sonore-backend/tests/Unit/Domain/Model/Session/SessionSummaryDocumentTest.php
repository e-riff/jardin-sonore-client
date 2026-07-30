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
    public function itMarksItsDocumentAsPendingWhenDetailsChange(): void
    {
        $sessionSummary = new SessionSummary(
            title: 'Matin musical',
            sessionDate: new DateTimeImmutable('2026-07-30'),
            organizationName: 'Crèche des Lilas',
        );
        $sessionSummary->markDocumentReady('/tmp/matin-musical.pdf');

        $sessionSummary->updateDetails(
            title: 'Matin musical',
            sessionDate: new DateTimeImmutable('2026-07-30'),
            organizationName: 'Crèche des Lilas',
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
