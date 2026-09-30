<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Session;

use App\Domain\Model\Session\SessionDocumentStatus;
use App\Domain\Model\Session\SessionSummary;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SessionFirstPublicationTest extends TestCase
{
    public function testFirstPublicationSetsItsDate(): void
    {
        $sessionSummary = new SessionSummary(title: 'Matin musical', sessionDate: new DateTimeImmutable('2026-09-30'));
        self::assertNull($sessionSummary->getFirstPublishedAt());
        $beforePublication = new DateTimeImmutable();

        $sessionSummary->setPublished(true);

        self::assertTrue($sessionSummary->isPublished());
        self::assertNotNull($sessionSummary->getFirstPublishedAt());
        self::assertGreaterThanOrEqual($beforePublication, $sessionSummary->getFirstPublishedAt());
        self::assertLessThanOrEqual(new DateTimeImmutable(), $sessionSummary->getFirstPublishedAt());
    }

    public function testRepublishingPreservesItsOriginalDate(): void
    {
        $sessionSummary = new SessionSummary(title: 'Matin musical', sessionDate: new DateTimeImmutable('2026-09-30'));
        $sessionSummary->setPublished(true);
        $firstPublishedAt = $sessionSummary->getFirstPublishedAt();

        $sessionSummary->setPublished(false);
        self::assertFalse($sessionSummary->isPublished());
        self::assertSame($firstPublishedAt, $sessionSummary->getFirstPublishedAt());
        $sessionSummary->setPublished(true);
        $sessionSummary->setPublished(true);

        self::assertSame($firstPublishedAt, $sessionSummary->getFirstPublishedAt());
    }

    public function testRestoringPublishedSessionPreservesItsDate(): void
    {
        $firstPublishedAt = new DateTimeImmutable('2026-09-25 10:00:00');
        $sessionSummary = new SessionSummary(
            title: 'Matin musical',
            sessionDate: new DateTimeImmutable('2026-09-25'),
            published: true,
            firstPublishedAt: $firstPublishedAt,
        );

        $sessionSummary->setPublished(true);

        self::assertSame($firstPublishedAt, $sessionSummary->getFirstPublishedAt());
    }

    public function testRestoringUnpublishedSessionPreservesItsPublicationHistory(): void
    {
        $firstPublishedAt = new DateTimeImmutable('2026-09-25 10:00:00');
        $sessionSummary = new SessionSummary(
            title: 'Matin musical',
            sessionDate: new DateTimeImmutable('2026-09-25'),
            published: false,
            firstPublishedAt: $firstPublishedAt,
        );

        $sessionSummary->setPublished(true);

        self::assertSame($firstPublishedAt, $sessionSummary->getFirstPublishedAt());
    }

    public function testChangingPublicationDoesNotRegenerateReadyPdf(): void
    {
        $sessionSummary = new SessionSummary(title: 'Matin musical', sessionDate: new DateTimeImmutable('2026-09-30'));
        $sessionSummary->markDocumentReady('/tmp/matin-musical.pdf');

        $sessionSummary->setPublished(true);
        $sessionSummary->setPublished(false);
        $sessionSummary->setPublished(true);

        self::assertNotNull($sessionSummary->getFirstPublishedAt());
        self::assertSame(SessionDocumentStatus::READY, $sessionSummary->getDocumentStatus());
        self::assertSame('/tmp/matin-musical.pdf', $sessionSummary->getDocumentPath());
    }
}
