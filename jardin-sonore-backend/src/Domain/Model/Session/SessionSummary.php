<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class SessionSummary implements UuidIdentifiableInterface
{
    use UuidIdentifiableTrait;

    private string $title;

    private DateTimeImmutable $sessionDate;

    private string $organizationName;

    private ?string $theme;

    private ?string $generalNotes;

    private ?string $materialSummary;

    private ?string $furtherExploration;

    /**
     * @var list<string>
     */
    private array $instrumentUuids;

    /**
     * @var list<SessionSequence>
     */
    private array $sequences;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    private SessionDocumentStatus $documentStatus;

    private ?string $documentPath;

    private ?string $documentError;

    /**
     * @param list<string>          $instrumentUuids
     * @param list<SessionSequence> $sequences
     */
    public function __construct(
        string $title,
        DateTimeImmutable $sessionDate,
        string $organizationName,
        ?string $theme = null,
        ?string $generalNotes = null,
        ?string $materialSummary = null,
        ?string $furtherExploration = null,
        array $instrumentUuids = [],
        array $sequences = [],
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?Uuid $uuid = null,
        SessionDocumentStatus $documentStatus = SessionDocumentStatus::PENDING,
        ?string $documentPath = null,
        ?string $documentError = null,
    ) {
        $this->initializeUuid($uuid);
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        $this->documentStatus = $documentStatus;
        $this->documentPath = self::normalizeNullableString($documentPath);
        $this->documentError = self::normalizeNullableString($documentError);
        $this->sequences = [];
        $this->updateDetails(
            title: $title,
            sessionDate: $sessionDate,
            organizationName: $organizationName,
            theme: $theme,
            generalNotes: $generalNotes,
            materialSummary: $materialSummary,
            furtherExploration: $furtherExploration,
            instrumentUuids: $instrumentUuids,
        );

        foreach ($sequences as $sessionSequence) {
            $this->sequences[] = $sessionSequence;
        }
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSessionDate(): DateTimeImmutable
    {
        return $this->sessionDate;
    }

    public function getOrganizationName(): string
    {
        return $this->organizationName;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function getGeneralNotes(): ?string
    {
        return $this->generalNotes;
    }

    public function getMaterialSummary(): ?string
    {
        return $this->materialSummary;
    }

    public function getFurtherExploration(): ?string
    {
        return $this->furtherExploration;
    }

    /**
     * @return list<string>
     */
    public function getInstrumentUuids(): array
    {
        return $this->instrumentUuids;
    }

    /**
     * @return list<SessionSequence>
     */
    public function getSequences(): array
    {
        return $this->sequences;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDocumentStatus(): SessionDocumentStatus
    {
        return $this->documentStatus;
    }

    public function getDocumentPath(): ?string
    {
        return $this->documentPath;
    }

    public function getDocumentError(): ?string
    {
        return $this->documentError;
    }

    public function markDocumentPending(): void
    {
        $this->documentStatus = SessionDocumentStatus::PENDING;
        $this->documentPath = null;
        $this->documentError = null;
    }

    public function markDocumentGenerating(): void
    {
        $this->documentStatus = SessionDocumentStatus::GENERATING;
        $this->documentError = null;
    }

    public function markDocumentReady(string $documentPath): void
    {
        if ('' === trim($documentPath)) {
            throw new InvalidArgumentException('Session document path cannot be blank.');
        }

        $this->documentStatus = SessionDocumentStatus::READY;
        $this->documentPath = trim($documentPath);
        $this->documentError = null;
    }

    public function markDocumentFailed(string $documentError): void
    {
        $this->documentStatus = SessionDocumentStatus::FAILED;
        $this->documentPath = null;
        $this->documentError = self::normalizeNullableString($documentError) ?? 'Session document generation failed.';
    }

    /**
     * @param list<string> $instrumentUuids
     */
    public function updateDetails(
        string $title,
        DateTimeImmutable $sessionDate,
        string $organizationName,
        ?string $theme,
        ?string $generalNotes,
        ?string $materialSummary,
        ?string $furtherExploration,
        array $instrumentUuids,
    ): void {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('Session summary title cannot be blank.');
        }

        $this->title = trim($title);
        $this->sessionDate = $sessionDate;
        $this->organizationName = trim($organizationName);
        $this->theme = self::normalizeNullableString($theme);
        $this->generalNotes = self::normalizeNullableString($generalNotes);
        $this->materialSummary = self::normalizeNullableString($materialSummary);
        $this->furtherExploration = self::normalizeNullableString($furtherExploration);
        $normalizedInstrumentUuids = array_map(
            static fn (string $uuid): string => trim($uuid),
            $instrumentUuids,
        );
        $this->instrumentUuids = array_values(array_unique(array_filter(
            $normalizedInstrumentUuids,
            static fn (string $uuid): bool => '' !== $uuid,
        )));
        $this->updatedAt = new DateTimeImmutable();
        $this->markDocumentPending();
    }

    public function addSequence(SessionSequence $sessionSequence): void
    {
        $this->sequences[] = $sessionSequence;
        $this->addSequenceInstruments($sessionSequence);
        $this->updatedAt = new DateTimeImmutable();
        $this->markDocumentPending();
    }

    public function removeInstrument(Uuid $instrumentUuid): void
    {
        $instrumentUuidString = $instrumentUuid->toRfc4122();
        $updatedInstrumentUuids = array_values(array_filter(
            $this->instrumentUuids,
            static fn (string $uuid): bool => $uuid !== $instrumentUuidString,
        ));

        if ($updatedInstrumentUuids === $this->instrumentUuids) {
            return;
        }

        $this->instrumentUuids = $updatedInstrumentUuids;
        $this->updatedAt = new DateTimeImmutable();
        $this->markDocumentPending();
    }

    public function replaceSequence(SessionSequence $sessionSequence): void
    {
        foreach ($this->sequences as $index => $existingSequence) {
            if ($existingSequence->uuid->equals($sessionSequence->uuid)) {
                $this->sequences[$index] = $sessionSequence;
                $this->addSequenceInstruments($sessionSequence);
                $this->updatedAt = new DateTimeImmutable();
                $this->markDocumentPending();

                return;
            }
        }

        throw new InvalidArgumentException('Session sequence not found.');
    }

    public function removeSequence(Uuid $sequenceUuid): void
    {
        $this->sequences = array_values(array_filter(
            $this->sequences,
            static fn (SessionSequence $sessionSequence): bool => !$sessionSequence->uuid->equals($sequenceUuid),
        ));
        $this->updatedAt = new DateTimeImmutable();
        $this->markDocumentPending();
    }

    public function moveSequenceUp(Uuid $sequenceUuid): void
    {
        $this->moveSequence($sequenceUuid, -1);
    }

    public function moveSequenceDown(Uuid $sequenceUuid): void
    {
        $this->moveSequence($sequenceUuid, 1);
    }

    /**
     * @param list<Uuid> $sequenceUuids
     */
    public function reorderSequences(array $sequenceUuids): void
    {
        $expectedSequenceUuids = array_map(
            static fn (SessionSequence $sessionSequence): string => $sessionSequence->uuid->toRfc4122(),
            $this->sequences,
        );
        $requestedSequenceUuids = array_map(
            static fn (Uuid $sequenceUuid): string => $sequenceUuid->toRfc4122(),
            $sequenceUuids,
        );

        if (count($expectedSequenceUuids) !== count($requestedSequenceUuids)
            || count($requestedSequenceUuids) !== count(array_unique($requestedSequenceUuids))
            || array_diff($expectedSequenceUuids, $requestedSequenceUuids)
            || array_diff($requestedSequenceUuids, $expectedSequenceUuids)) {
            throw new InvalidArgumentException('Session sequence order must be an exact permutation.');
        }

        $sequencesByUuid = [];
        foreach ($this->sequences as $sessionSequence) {
            $sequencesByUuid[$sessionSequence->uuid->toRfc4122()] = $sessionSequence;
        }

        $this->sequences = array_map(
            static fn (string $sequenceUuid): SessionSequence => $sequencesByUuid[$sequenceUuid],
            $requestedSequenceUuids,
        );
        $this->updatedAt = new DateTimeImmutable();
        $this->markDocumentPending();
    }

    private function moveSequence(Uuid $sequenceUuid, int $direction): void
    {
        foreach ($this->sequences as $index => $sessionSequence) {
            if (!$sessionSequence->uuid->equals($sequenceUuid)) {
                continue;
            }

            $targetIndex = $index + $direction;

            if (!isset($this->sequences[$targetIndex])) {
                return;
            }

            [$this->sequences[$index], $this->sequences[$targetIndex]] = [$this->sequences[$targetIndex], $this->sequences[$index]];
            $this->updatedAt = new DateTimeImmutable();
            $this->markDocumentPending();

            return;
        }
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmedValue = trim($value);

        return '' === $trimmedValue ? null : $trimmedValue;
    }

    private function addSequenceInstruments(SessionSequence $sessionSequence): void
    {
        $this->instrumentUuids = array_values(array_unique([
            ...$this->instrumentUuids,
            ...$sessionSequence->instrumentUuids,
        ]));
    }
}
