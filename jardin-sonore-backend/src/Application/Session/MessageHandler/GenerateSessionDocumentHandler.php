<?php

declare(strict_types=1);

namespace App\Application\Session\MessageHandler;

use App\Application\Session\Message\GenerateSessionDocumentMessage;
use App\Application\Session\SessionDocumentGeneratorInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[AsMessageHandler]
#[WithMonologChannel('session_document')]
final readonly class GenerateSessionDocumentHandler
{
    public function __construct(
        private SessionSummaryRepositoryInterface $sessionSummaryRepository,
        private SessionDocumentGeneratorInterface $sessionDocumentGenerator,
        private LoggerInterface $sessionDocumentLogger,
    ) {
    }

    public function __invoke(GenerateSessionDocumentMessage $generateSessionDocumentMessage): void
    {
        if (!Uuid::isValid($generateSessionDocumentMessage->sessionUuid)) {
            return;
        }

        $sessionSummary = $this->sessionSummaryRepository->findByUuid(Uuid::fromString($generateSessionDocumentMessage->sessionUuid));
        if (null === $sessionSummary) {
            return;
        }

        if ('ready' === $sessionSummary->getDocumentStatus()->value
            && null !== $sessionSummary->getDocumentPath()
            && is_file($sessionSummary->getDocumentPath())) {
            return;
        }

        $sessionSummary->markDocumentGenerating();
        $this->sessionSummaryRepository->save($sessionSummary, false);
        $this->sessionDocumentLogger->info('Session document generation started.', ['session_uuid' => $generateSessionDocumentMessage->sessionUuid]);

        try {
            $documentPath = $this->sessionDocumentGenerator->generate($sessionSummary);
            $sessionSummary->markDocumentReady($documentPath);
            $this->sessionSummaryRepository->save($sessionSummary, false);
            $this->sessionDocumentLogger->info('Session document generation completed.', ['session_uuid' => $generateSessionDocumentMessage->sessionUuid, 'document_path' => $documentPath]);
        } catch (Throwable $throwable) {
            $sessionSummary->markDocumentFailed($throwable->getMessage());
            $this->sessionSummaryRepository->save($sessionSummary, false);
            $this->sessionDocumentLogger->error('Session document generation failed.', ['session_uuid' => $generateSessionDocumentMessage->sessionUuid, 'exception' => $throwable]);
        }
    }
}
