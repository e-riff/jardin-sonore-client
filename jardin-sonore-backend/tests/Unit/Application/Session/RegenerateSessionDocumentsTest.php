<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\RegenerateSessionDocument;
use App\Application\Session\RegenerateSessionDocuments;
use App\Domain\Model\Session\SessionDocumentStatus;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RegenerateSessionDocumentsTest extends TestCase
{
    public function testItMarksOneExistingSessionPendingAndSchedulesItsDocumentGeneration(): void
    {
        $sessionSummary = $this->sessionSummary('Séance individuelle');
        $sessionSummary->markDocumentReady('/tmp/session.pdf');
        $sessionSummaryRepository = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessionSummaryRepository->expects(self::once())->method('findByUuid')->with($sessionSummary->getUuid())->willReturn($sessionSummary);
        $sessionSummaryRepository->expects(self::once())->method('save')->with(
            self::callback(static fn (SessionSummary $savedSessionSummary): bool => SessionDocumentStatus::PENDING === $savedSessionSummary->getDocumentStatus()),
            true,
        );

        (new RegenerateSessionDocument($sessionSummaryRepository))($sessionSummary->getUuid());
    }

    public function testItMarksEverySessionPendingAndSchedulesEachDocumentGeneration(): void
    {
        $firstSessionSummary = $this->sessionSummary('Première séance');
        $firstSessionSummary->markDocumentReady('/tmp/first-session.pdf');
        $secondSessionSummary = $this->sessionSummary('Seconde séance');
        $sessionSummaryRepository = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessionSummaryRepository->expects(self::once())->method('search')->with(null)->willReturn([$firstSessionSummary, $secondSessionSummary]);
        $sessionSummaryRepository->expects(self::exactly(2))->method('save')->with(
            self::callback(static fn (SessionSummary $savedSessionSummary): bool => SessionDocumentStatus::PENDING === $savedSessionSummary->getDocumentStatus()),
            true,
        );

        (new RegenerateSessionDocuments($sessionSummaryRepository))();
    }

    private function sessionSummary(string $title): SessionSummary
    {
        return new SessionSummary(
            title: $title,
            sessionDate: new DateTimeImmutable('2026-09-23'),
            organizations: [],
        );
    }
}
