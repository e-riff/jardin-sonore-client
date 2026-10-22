<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Commercial;

use App\Application\Commercial\CommercialAmountParser;
use App\Application\Commercial\RecordCommercialQuote;
use App\Application\Mcp\ProjectQuoteManager;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialQuoteEntity;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;

final readonly class DoctrineProjectQuoteManager implements ProjectQuoteManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RecordCommercialQuote $quoteRecorder,
    ) {
    }

    public function list(int $projectId): array
    {
        $quoteEntities = array_slice(array_reverse($this->project($projectId)->getQuotes()->toArray()), 0, 50);

        return array_map(fn (CommercialQuoteEntity $quoteEntity): array => $this->result($quoteEntity), $quoteEntities);
    }

    public function get(int $projectId, int $quoteId): array
    {
        return $this->result($this->quote($projectId, $quoteId));
    }

    public function execute(string $operation, array $arguments): array
    {
        $projectId = $this->positiveId($arguments, 'projectId');
        $projectEntity = $this->project($projectId);

        return match ($operation) {
            'record_project_quote_sent' => $this->recordSent($projectEntity, $arguments),
            'mark_project_quote_signed' => $this->markSigned($projectId, $arguments),
            'mark_project_quote_refused' => $this->markRefused($projectId, $arguments),
            default => throw new DomainException('Unknown quote operation.'),
        };
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function recordSent(CommercialProjectEntity $projectEntity, array $arguments): array
    {
        $reference = $this->text($arguments, 'reference', 64);
        $filename = $this->text($arguments, 'filename', 255);
        $amount = $this->text($arguments, 'amount', 32);
        $amountCents = CommercialAmountParser::euroCents($amount);
        if (null === $amountCents) {
            throw new DomainException('Invalid quote amount; use euros with at most two decimals.');
        }
        $sentOn = $this->date($arguments, 'sentOn');
        $replacesQuoteEntity = null;
        if (array_key_exists('replacesQuoteId', $arguments) && null !== $arguments['replacesQuoteId']) {
            $replacesQuoteEntity = $this->quote($projectEntity->getId(), $this->positiveId($arguments, 'replacesQuoteId'));
        }

        $existingQuoteEntity = $this->entityManager->getRepository(CommercialQuoteEntity::class)->findOneBy(['reference' => $reference]);
        if ($existingQuoteEntity instanceof CommercialQuoteEntity) {
            if ($existingQuoteEntity->getProject() === $projectEntity
                && $existingQuoteEntity->getFilename() === $filename
                && $existingQuoteEntity->getAmountCents() === $amountCents
                && $existingQuoteEntity->getSentOn()->format('Y-m-d') === $sentOn->format('Y-m-d')
                && $existingQuoteEntity->getReplaces()?->getId() === $replacesQuoteEntity?->getId()) {
                return $this->result($existingQuoteEntity);
            }
            throw new DomainException('This quote reference is already used.');
        }

        $quoteEntity = $this->quoteRecorder->sent($projectEntity, $reference, $filename, $amountCents, $sentOn, $replacesQuoteEntity);
        $this->entityManager->flush();

        return $this->result($quoteEntity);
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function markSigned(int $projectId, array $arguments): array
    {
        $quoteEntity = $this->quote($projectId, $this->positiveId($arguments, 'quoteId'));
        $this->assertRevision($quoteEntity, $this->text($arguments, 'revision', 64));
        $this->quoteRecorder->markSigned($quoteEntity, $this->date($arguments, 'occurredOn'));
        $this->entityManager->flush();

        return $this->result($quoteEntity);
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function markRefused(int $projectId, array $arguments): array
    {
        $quoteEntity = $this->quote($projectId, $this->positiveId($arguments, 'quoteId'));
        $this->assertRevision($quoteEntity, $this->text($arguments, 'revision', 64));
        $this->quoteRecorder->markRefused($quoteEntity, $this->date($arguments, 'occurredOn'));
        $this->entityManager->flush();

        return $this->result($quoteEntity);
    }

    private function project(int $projectId): CommercialProjectEntity
    {
        $projectEntity = $this->entityManager->find(CommercialProjectEntity::class, $projectId);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw new DomainException('Dossier not found.');
        }

        return $projectEntity;
    }

    private function quote(int $projectId, int $quoteId): CommercialQuoteEntity
    {
        $quoteEntity = $this->entityManager->find(CommercialQuoteEntity::class, $quoteId);
        if (!$quoteEntity instanceof CommercialQuoteEntity || $quoteEntity->getProject()->getId() !== $projectId) {
            throw new DomainException('Quote not found in this dossier.');
        }

        return $quoteEntity;
    }

    /** @return array<string, mixed> */
    private function result(CommercialQuoteEntity $quoteEntity): array
    {
        $projectId = $quoteEntity->getProject()->getId();
        $quoteId = $quoteEntity->getId();
        if (null === $projectId || null === $quoteId) {
            throw new DomainException('Quote has not been persisted.');
        }
        $revisionData = [
            $quoteId,
            $quoteEntity->getReference(),
            $quoteEntity->getFilename(),
            $quoteEntity->getAmountCents(),
            $quoteEntity->getSentOn()->format(DATE_ATOM),
            $quoteEntity->getSignedOn()?->format(DATE_ATOM),
            $quoteEntity->getRefusedOn()?->format(DATE_ATOM),
            $quoteEntity->getReplaces()?->getId(),
        ];

        return [
            'projectId' => $projectId,
            'quoteId' => $quoteId,
            'reference' => $quoteEntity->getReference(),
            'filename' => $quoteEntity->getFilename(),
            'amountCents' => $quoteEntity->getAmountCents(),
            'sentOn' => $quoteEntity->getSentOn()->format('Y-m-d'),
            'status' => null !== $quoteEntity->getSignedOn() ? 'signed' : (null !== $quoteEntity->getRefusedOn() ? 'refused' : 'sent'),
            'signedOn' => $quoteEntity->getSignedOn()?->format('Y-m-d'),
            'refusedOn' => $quoteEntity->getRefusedOn()?->format('Y-m-d'),
            'replacesQuoteId' => $quoteEntity->getReplaces()?->getId(),
            'revision' => hash('sha256', json_encode($revisionData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
    }

    private function assertRevision(CommercialQuoteEntity $quoteEntity, string $revision): void
    {
        if (!hash_equals($this->result($quoteEntity)['revision'], $revision)) {
            throw new DomainException('The quote changed; read it again before retrying.');
        }
    }

    /** @param array<string, mixed> $arguments */
    private function positiveId(array $arguments, string $key): int
    {
        $value = $arguments[$key] ?? null;
        if (!is_int($value) || 1 > $value) {
            throw new DomainException("Invalid {$key}.");
        }

        return $value;
    }

    /** @param array<string, mixed> $arguments */
    private function text(array $arguments, string $key, int $maxLength): string
    {
        $value = $arguments[$key] ?? null;
        if (!is_string($value) || '' === trim($value) || mb_strlen($value) > $maxLength || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw new DomainException("Invalid {$key}.");
        }

        return trim($value);
    }

    /** @param array<string, mixed> $arguments */
    private function date(array $arguments, string $key): DateTimeImmutable
    {
        $value = $arguments[$key] ?? null;
        if (!is_string($value) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw new DomainException("Invalid {$key} date.");
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Paris'));
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new DomainException("Invalid {$key} date.");
        }

        return $date;
    }
}
