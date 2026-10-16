<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;
use DomainException;

class CommercialInterventionEntity
{
    use IdentifiableTrait;

    public const string STATUS_OPTION = 'option';
    public const string STATUS_CONFIRMED = 'confirmed';
    public const string STATUS_CANCELED = 'canceled';

    private string $status = self::STATUS_OPTION;

    private ?DateTimeImmutable $canceledAt = null;

    private ?string $syncError = null;

    public function __construct(
        private ?CommercialProjectEntity $project,
        private string $calendarId,
        private string $googleEventId,
        private string $title,
        private DateTimeImmutable $startsAt,
        private DateTimeImmutable $endsAt,
        private string $timeZone,
        private ?string $location,
        private string $etag,
        private DateTimeImmutable $syncedAt,
        private ?string $recurringEventId = null,
        private ?DateTimeImmutable $originalStartsAt = null,
    ) {
        self::assertTimeRange($startsAt, $endsAt);
    }

    public function getProject(): ?CommercialProjectEntity
    {
        return $this->project;
    }

    public function assignTo(CommercialProjectEntity $projectEntity): void
    {
        $this->project = $projectEntity;
    }

    public function detach(): void
    {
        $this->project = null;
    }

    public function getCalendarId(): string
    {
        return $this->calendarId;
    }

    public function getGoogleEventId(): string
    {
        return $this->googleEventId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStartsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getTimeZone(): string
    {
        return $this->timeZone;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getEtag(): string
    {
        return $this->etag;
    }

    public function getSyncedAt(): DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function getRecurringEventId(): ?string
    {
        return $this->recurringEventId;
    }

    public function getOriginalStartsAt(): ?DateTimeImmutable
    {
        return $this->originalStartsAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCanceledAt(): ?DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function getSyncError(): ?string
    {
        return $this->syncError;
    }

    public function confirm(): void
    {
        $this->status = self::STATUS_CONFIRMED;
    }

    public function cancel(DateTimeImmutable $now): void
    {
        $this->status = self::STATUS_CANCELED;
        $this->canceledAt = $now;
        $this->syncError = null;
    }

    public function markSyncFailed(string $error): void
    {
        $this->syncError = $error;
    }

    public function refreshFromGoogle(
        string $title,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        string $timeZone,
        ?string $location,
        string $etag,
        DateTimeImmutable $syncedAt,
    ): void {
        self::assertTimeRange($startsAt, $endsAt);
        $this->title = $title;
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
        $this->timeZone = $timeZone;
        $this->location = $location;
        $this->etag = $etag;
        $this->syncedAt = $syncedAt;
        $this->syncError = null;
    }

    private static function assertTimeRange(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): void
    {
        if ($endsAt <= $startsAt) {
            throw new DomainException('The intervention end must follow its start.');
        }
    }
}
