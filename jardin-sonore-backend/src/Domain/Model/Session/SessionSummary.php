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
    ) {
        $this->initializeUuid($uuid);
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
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

        if ('' === trim($organizationName)) {
            throw new InvalidArgumentException('Session summary organization name cannot be blank.');
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
    }

    public function addSequence(SessionSequence $sessionSequence): void
    {
        $this->sequences[] = $sessionSequence;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function replaceSequence(SessionSequence $sessionSequence): void
    {
        foreach ($this->sequences as $index => $existingSequence) {
            if ($existingSequence->uuid->equals($sessionSequence->uuid)) {
                $this->sequences[$index] = $sessionSequence;
                $this->updatedAt = new DateTimeImmutable();

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
    }

    public function moveSequenceUp(Uuid $sequenceUuid): void
    {
        $this->moveSequence($sequenceUuid, -1);
    }

    public function moveSequenceDown(Uuid $sequenceUuid): void
    {
        $this->moveSequence($sequenceUuid, 1);
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
}
