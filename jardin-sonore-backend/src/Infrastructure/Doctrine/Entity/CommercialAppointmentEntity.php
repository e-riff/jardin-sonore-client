<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Application\Commercial\CalendarEventOccurrence;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;

class CommercialAppointmentEntity
{
    use IdentifiableTrait;

    public const string STATUS_SCHEDULED = 'scheduled';
    public const string STATUS_CANCELED = 'canceled';

    private string $status = self::STATUS_SCHEDULED;

    private ?DateTimeImmutable $canceledAt = null;

    private ?string $summary = null;

    private ?string $syncError = null;

    private int $revision = 1;

    public function __construct(
        private CommercialProjectEntity $project,
        private string $calendarId,
        private string $requestId,
        CalendarEventOccurrence $event,
        private DateTimeImmutable $syncedAt,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $requestId)) {
            throw new DomainException('Invalid appointment request ID.');
        }
        $this->googleEventId = $event->id;
        $this->title = $event->title;
        $this->startsAt = $event->startsAt->setTimezone(new DateTimeZone('UTC'));
        $this->endsAt = $event->endsAt->setTimezone(new DateTimeZone('UTC'));
        $this->timeZone = $event->timeZone;
        $this->location = $event->location;
        $this->etag = $event->etag;
    }

    private string $googleEventId;

    private string $title;

    private DateTimeImmutable $startsAt;

    private DateTimeImmutable $endsAt;

    private string $timeZone;

    private ?string $location;

    private string $etag;

    public function getProject(): CommercialProjectEntity
    {
        return $this->project;
    }

    public function getCalendarId(): string
    {
        return $this->calendarId;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCanceledAt(): ?DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getSyncError(): ?string
    {
        return $this->syncError;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function refreshFromGoogle(CalendarEventOccurrence $event, DateTimeImmutable $now): bool
    {
        if ($event->id !== $this->googleEventId) {
            throw new DomainException('A different Calendar event cannot replace this appointment.');
        }
        $changed = $event->title !== $this->title
            || $event->startsAt->getTimestamp() !== $this->startsAt->getTimestamp()
            || $event->endsAt->getTimestamp() !== $this->endsAt->getTimestamp()
            || $event->timeZone !== $this->timeZone
            || $event->location !== $this->location
            || $event->etag !== $this->etag;
        if (!$changed && null === $this->syncError) {
            return false;
        }
        $this->title = $event->title;
        $this->startsAt = $event->startsAt->setTimezone(new DateTimeZone('UTC'));
        $this->endsAt = $event->endsAt->setTimezone(new DateTimeZone('UTC'));
        $this->timeZone = $event->timeZone;
        $this->location = $event->location;
        $this->etag = $event->etag;
        $this->syncedAt = $now;
        $this->syncError = null;
        if ($changed) {
            ++$this->revision;
        }

        return $changed;
    }

    public function cancel(DateTimeImmutable $now): void
    {
        if (self::STATUS_CANCELED === $this->status) {
            return;
        }
        $this->status = self::STATUS_CANCELED;
        $this->canceledAt = $now;
        $this->syncError = null;
        ++$this->revision;
    }

    public function setSummary(?string $summary): void
    {
        $summary = null === $summary ? null : trim($summary);
        if (null !== $summary && 10000 < mb_strlen($summary)) {
            throw new DomainException('Appointment summary is too long.');
        }
        $this->summary = '' === $summary ? null : $summary;
        ++$this->revision;
    }

    public function markSyncFailed(): void
    {
        $this->syncError = 'google_sync_failed';
    }
}
