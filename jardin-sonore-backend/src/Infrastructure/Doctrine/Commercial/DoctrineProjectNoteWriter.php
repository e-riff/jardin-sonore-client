<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Commercial;

use App\Application\Mcp\ProjectNoteWriter;
use App\Infrastructure\Doctrine\Entity\CommercialEventEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Mailing\NewsletterRichTextFormatter;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DoctrineProjectNoteWriter implements ProjectNoteWriter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NewsletterRichTextFormatter $newsletterRichTextFormatter,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /** @return array<string, mixed> */
    public function create(int $projectId, string $requestId, string $content, ?DateTimeImmutable $occurredOn): array
    {
        $formatted = $this->newsletterRichTextFormatter->format($content);
        if ('' === trim($formatted->text)) {
            throw new InvalidArgumentException('Project note cannot be empty after formatting.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($projectId, $requestId, $formatted, $occurredOn): array {
            $projectEntity = $this->entityManager->find(CommercialProjectEntity::class, $projectId, LockMode::PESSIMISTIC_WRITE);
            if (!$projectEntity instanceof CommercialProjectEntity) {
                throw new InvalidArgumentException('Project not found.');
            }

            foreach ($projectEntity->getEvents() as $eventEntity) {
                if (!$eventEntity instanceof CommercialEventEntity || ($eventEntity->getMetadata()['mcpRequestId'] ?? null) !== $requestId) {
                    continue;
                }
                if ('note' !== $eventEntity->getType()
                    || $eventEntity->getContent() !== $formatted->html
                    || ($eventEntity->getMetadata()['requestedOn'] ?? '') !== ($occurredOn?->format('Y-m-d') ?? '')) {
                    throw new InvalidArgumentException('This requestId already belongs to another note.');
                }

                return $this->result($projectId, $eventEntity->getId(), true);
            }

            $now = new DateTimeImmutable();
            $occurredAt = null === $occurredOn
                ? $now
                : $occurredOn->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
            $eventEntity = $projectEntity->recordEvent('note', $occurredAt, $formatted->html, [
                'source' => 'mcp',
                'mcpRequestId' => $requestId,
                'requestedOn' => $occurredOn?->format('Y-m-d') ?? '',
            ]);
            $this->entityManager->persist($eventEntity);
            $this->entityManager->flush();

            return $this->result($projectId, $eventEntity->getId(), false);
        });
    }

    /** @return array<string, mixed> */
    private function result(int $projectId, int $eventId, bool $alreadyExists): array
    {
        return [
            'projectId' => $projectId,
            'eventId' => $eventId,
            'alreadyExists' => $alreadyExists,
            'url' => $this->urlGenerator->generate('commercial_project_show', ['id' => $projectId], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}
