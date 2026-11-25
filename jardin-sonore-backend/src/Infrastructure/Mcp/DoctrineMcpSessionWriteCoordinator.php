<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use App\Application\Mcp\McpSessionWriteCoordinator;
use App\Application\Session\Message\GenerateSessionDocumentMessage;
use App\Infrastructure\Doctrine\Entity\SessionFamilyEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineMcpSessionWriteCoordinator implements McpSessionWriteCoordinator
{
    public function __construct(private EntityManagerInterface $entityManager, private MessageBusInterface $messageBus)
    {
    }

    public function execute(array $sessionUuids, ?string $familyUuid, callable $operation): array
    {
        $targetUuid = $sessionUuids[0] ?? null;
        $result = $this->entityManager->wrapInTransaction(function (EntityManagerInterface $manager) use ($sessionUuids, $familyUuid, $operation): array {
            if (null !== $familyUuid) {
                if (!Uuid::isValid($familyUuid)) {
                    throw new InvalidArgumentException('Invalid family UUID.');
                }
                $family = $manager->getRepository(SessionFamilyEntity::class)->findOneBy(['uuid' => Uuid::fromString($familyUuid)]);
                if (!$family instanceof SessionFamilyEntity) {
                    throw new InvalidArgumentException('Family not found.');
                }
                $manager->refresh($family, LockMode::PESSIMISTIC_WRITE);
            }
            $sessionUuids = array_values(array_unique($sessionUuids));
            sort($sessionUuids);
            foreach ($sessionUuids as $uuid) {
                if (!Uuid::isValid($uuid)) {
                    throw new InvalidArgumentException('Invalid session UUID.');
                }
                $session = $manager->getRepository(SessionSummaryEntity::class)->findOneBy(['uuid' => Uuid::fromString($uuid)]);
                if (!$session instanceof SessionSummaryEntity) {
                    throw new InvalidArgumentException('Session not found.');
                }
                $manager->refresh($session, LockMode::PESSIMISTIC_WRITE);
            }

            return $operation();
        });

        return null === $targetUuid ? $result : $this->scheduleDocument($targetUuid, $result);
    }

    public function executeCreation(string $tool, string $requestId, callable $operation): array
    {
        $connection = $this->entityManager->getConnection();
        $lockName = hash('sha256', $tool . ':' . $requestId);
        if (1 !== (int) $connection->fetchOne('SELECT GET_LOCK(?, 10)', [$lockName])) {
            throw new RuntimeException('Unable to lock creation request.');
        }
        try {
            $result = $operation();
            if ('create_session_draft' === $tool && !($result['isError'] ?? false)) {
                try {
                    $payload = json_decode($result['content'][0]['text'] ?? '', true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($payload) && isset($payload['uuid']) && is_string($payload['uuid']) && Uuid::isValid($payload['uuid'])) {
                        return $this->scheduleDocument($payload['uuid'], $result);
                    }
                } catch (JsonException) {
                    $result['content'][] = ['type' => 'text', 'text' => 'Saved, but document scheduling could not be verified. Do not create another session; reread it and regenerate the PDF from the backoffice.'];
                }
            }

            return $result;
        } finally {
            try {
                $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
            } catch (Throwable) {
                $connection->close();
            }
        }
    }

    /** @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function scheduleDocument(string $uuid, array $result): array
    {
        if ($result['isError'] ?? false) {
            return $result;
        }
        try {
            $target = $this->entityManager->getRepository(SessionSummaryEntity::class)->findOneBy(['uuid' => Uuid::fromString($uuid)]);
            if ($target instanceof SessionSummaryEntity && 'pending' === $target->getDocumentStatus()->value) {
                $this->messageBus->dispatch(new GenerateSessionDocumentMessage($uuid));
            }
        } catch (Throwable) {
            $result['content'][] = ['type' => 'text', 'text' => 'Saved, but PDF scheduling failed. Do not repeat the write; reread the session and regenerate its PDF from the backoffice.'];
        }

        return $result;
    }
}
