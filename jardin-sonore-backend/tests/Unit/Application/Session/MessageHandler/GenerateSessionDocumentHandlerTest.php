<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session\MessageHandler;

use App\Application\Session\Message\GenerateSessionDocumentMessage;
use App\Application\Session\MessageHandler\GenerateSessionDocumentHandler;
use App\Application\Session\SessionDocumentGenerationStore;
use App\Application\Session\SessionDocumentGeneratorInterface;
use App\Domain\Model\Session\SessionSummary;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class GenerateSessionDocumentHandlerTest extends TestCase
{
    public function testItSkipsAnAlreadyGeneratedDocumentWhenDuplicateMessagesRemainInTheQueue(): void
    {
        $documentPath = tempnam(sys_get_temp_dir(), 'jardin-sonore-session-document-');
        self::assertNotFalse($documentPath);
        file_put_contents($documentPath, 'pdf');
        $sessionSummary = new SessionSummary(
            title: 'Séance prête',
            sessionDate: new DateTimeImmutable('2026-07-31'),
            organizations: [],
        );
        $sessionSummary->markDocumentReady($documentPath);
        $sessionSummaryRepository = $this->createMock(SessionDocumentGenerationStore::class);
        $sessionSummaryRepository
            ->expects(self::once())
            ->method('claimDocument')
            ->willReturn(null);
        $sessionSummaryRepository->expects(self::never())->method('finishDocument');
        $sessionDocumentGenerator = $this->createMock(SessionDocumentGeneratorInterface::class);
        $sessionDocumentGenerator->expects(self::never())->method('generate');
        $logger = $this->createMock(LoggerInterface::class);

        try {
            (new GenerateSessionDocumentHandler($sessionSummaryRepository, $sessionDocumentGenerator, $logger))(
                new GenerateSessionDocumentMessage($sessionSummary->getUuid()->toRfc4122()),
            );
        } finally {
            unlink($documentPath);
        }
    }
}
