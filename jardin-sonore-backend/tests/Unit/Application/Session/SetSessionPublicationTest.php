<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\SetSessionPublication;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SetSessionPublicationTest extends TestCase
{
    public function testItPublishesAnExistingSessionWithoutSchedulingPdfGeneration(): void
    {
        $sessionSummary = new SessionSummary(title: 'Atelier', sessionDate: new DateTimeImmutable('2026-09-25'));
        $sessionSummaryRepository = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessionSummaryRepository->method('findByUuid')->with($sessionSummary->getUuid())->willReturn($sessionSummary);
        $sessionSummaryRepository->expects(self::once())->method('save')->with($sessionSummary, false);

        $published = (new SetSessionPublication($sessionSummaryRepository))($sessionSummary->getUuid(), true);

        self::assertTrue($published);
        self::assertTrue($sessionSummary->isPublished());
    }

    public function testItReturnsNullForAnUnknownSession(): void
    {
        $sessionSummaryRepository = $this->createMock(SessionSummaryRepositoryInterface::class);
        $sessionSummaryRepository->method('findByUuid')->willReturn(null);
        $sessionSummaryRepository->expects(self::never())->method('save');

        self::assertNull((new SetSessionPublication($sessionSummaryRepository))(Uuid::v4(), true));
    }
}
