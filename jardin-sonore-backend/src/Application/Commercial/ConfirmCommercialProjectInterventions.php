<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Repository\CommercialInterventionDoctrineRepository;
use App\Infrastructure\Google\GoogleCalendarApiException;
use App\Infrastructure\Google\GoogleCalendarConflictException;
use App\Infrastructure\Google\GoogleCalendarOAuthException;
use Symfony\Component\Clock\ClockInterface;

/** @see RecordCommercialQuote::markSigned() */
final readonly class ConfirmCommercialProjectInterventions
{
    public function __construct(
        private CommercialInterventionDoctrineRepository $interventionRepository,
        private CommercialCalendarGateway $calendarGateway,
        private ClockInterface $clock,
    ) {
    }

    /** @return array{confirmed: int, failed: int} */
    public function forProject(CommercialProjectEntity $commercialProjectEntity): array
    {
        $result = ['confirmed' => 0, 'failed' => 0];
        if (null === $commercialProjectEntity->getId() || CommercialProjectEntity::STATUS_CONFIRMED !== $commercialProjectEntity->getStatus()) {
            return $result;
        }
        foreach ($this->interventionRepository->optionsForProject($commercialProjectEntity) as $interventionEntity) {
            try {
                $currentEvent = $this->calendarGateway->event($interventionEntity->getGoogleEventId());
                if (null === $currentEvent || 'cancelled' === $currentEvent->googleStatus || $currentEvent->allDay) {
                    $interventionEntity->markSyncFailed('google_event_unavailable');
                    ++$result['failed'];
                    continue;
                }
                $title = CommercialInterventionTitle::withoutOptionMarker($currentEvent->title);
                if ('' === $title) {
                    $interventionEntity->markSyncFailed('google_event_invalid_title');
                    ++$result['failed'];
                    continue;
                }
                // Use the fresh Google snapshot and ETag to preserve external schedule changes.
                $updatedEvent = $title === $currentEvent->title ? $currentEvent : $this->calendarGateway->updateEvent($currentEvent, new CalendarEventDraft($title, $currentEvent->startsAt, $currentEvent->endsAt, $currentEvent->timeZone, $currentEvent->location));
                if (CommercialInterventionTitle::hasOptionMarker($updatedEvent->title)) {
                    $interventionEntity->markSyncFailed('google_option_marker_retained');
                    ++$result['failed'];
                    continue;
                }
                $interventionEntity->refreshFromGoogle($title, $updatedEvent->startsAt, $updatedEvent->endsAt, $updatedEvent->timeZone, $updatedEvent->location, $updatedEvent->etag, $this->clock->now());
                $interventionEntity->setStatus(CommercialInterventionEntity::STATUS_CONFIRMED);
                ++$result['confirmed'];
            } catch (GoogleCalendarConflictException) {
                $interventionEntity->markSyncFailed('google_version_conflict');
                ++$result['failed'];
            } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
                $interventionEntity->markSyncFailed('google_sync_failed');
                ++$result['failed'];
            }
        }

        return $result;
    }
}
