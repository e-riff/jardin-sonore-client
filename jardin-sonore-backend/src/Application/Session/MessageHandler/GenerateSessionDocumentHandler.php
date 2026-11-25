<?php

declare(strict_types=1);

namespace App\Application\Session\MessageHandler;

use App\Application\Session\Message\GenerateSessionDocumentMessage;
use App\Application\Session\SessionDocumentGenerationStore;
use App\Application\Session\SessionDocumentGeneratorInterface;
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
        private SessionDocumentGenerationStore $generationStore,
        private SessionDocumentGeneratorInterface $sessionDocumentGenerator,
        private LoggerInterface $sessionDocumentLogger,
    ) {
    }

    public function __invoke(GenerateSessionDocumentMessage $generateSessionDocumentMessage): void
    {
        if (!Uuid::isValid($generateSessionDocumentMessage->sessionUuid)) {
            return;
        }

        $uuid = Uuid::fromString($generateSessionDocumentMessage->sessionUuid);
        $token = Uuid::v4()->toRfc4122();
        $sessionSummary = $this->generationStore->claimDocument($uuid, $token);
        if (null === $sessionSummary) {
            return;
        }

        $stagedPath = null;
        try {
            $stagedPath = $this->sessionDocumentGenerator->generate($sessionSummary);
            $this->generationStore->finishDocument($uuid, $token, $stagedPath, null);
        } catch (Throwable $throwable) {
            $this->generationStore->finishDocument($uuid, $token, null, $throwable->getMessage());
            $this->sessionDocumentLogger->error('Session document generation failed.', ['session_uuid' => $uuid, 'exception' => $throwable]);
        } finally {
            if (null !== $stagedPath && is_file($stagedPath)) {
                unlink($stagedPath);
            }
        }
    }
}
