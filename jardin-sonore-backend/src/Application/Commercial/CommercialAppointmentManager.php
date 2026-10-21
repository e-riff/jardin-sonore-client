<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Google\GoogleCalendarConflictException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CommercialAppointmentManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommercialCalendarGateway $calendarGateway,
        private ClockInterface $clock,
        #[Autowire('%app.commercial.calendar_id%')] private string $calendarId,
        private TranslatorInterface $translator,
    ) {
    }

    public function create(CommercialProjectEntity $projectEntity, CalendarEventDraft $draft, string $requestId): CommercialAppointmentEntity
    {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $requestId)) {
            throw new DomainException('Invalid appointment request ID.');
        }
        $projectId = $projectEntity->getId();
        if (null === $projectId) {
            throw new DomainException('The appointment needs a persisted dossier.');
        }
        $repository = $this->entityManager->getRepository(CommercialAppointmentEntity::class);
        $existingEntity = $repository->findOneBy(['calendarId' => $this->calendarId, 'requestId' => $requestId]);
        if ($existingEntity instanceof CommercialAppointmentEntity) {
            if ($existingEntity->getProject()->getId() !== $projectEntity->getId()) {
                throw new DomainException('The appointment request belongs to another dossier.');
            }

            return $existingEntity;
        }

        $markedDraft = $this->markedDraft($draft);
        $googleRequestId = substr(hash('sha256', "{$projectId}:{$requestId}"), 0, 32);
        $event = $this->calendarGateway->createEvent($markedDraft, $googleRequestId);
        $linkedEntity = $repository->findOneBy(['calendarId' => $this->calendarId, 'googleEventId' => $event->id]);
        if ($linkedEntity instanceof CommercialAppointmentEntity) {
            if ($linkedEntity->getProject()->getId() !== $projectEntity->getId() || $linkedEntity->getRequestId() !== $requestId) {
                throw new DomainException('The Calendar event is linked to another appointment.');
            }

            return $linkedEntity;
        }
        $appointmentEntity = new CommercialAppointmentEntity($projectEntity, $this->calendarId, $requestId, $event, $this->clock->now());
        $this->entityManager->persist($appointmentEntity);
        $projectEntity->recordEvent('appointment_created', $this->clock->now(), $this->translator->trans('appointment.created_in_dossier', [
            '%title%' => $event->title,
            '%date%' => $this->formatWhen($event->startsAt),
        ], 'commercial'));
        $this->entityManager->flush();

        return $appointmentEntity;
    }

    public function update(CommercialAppointmentEntity $appointmentEntity, CalendarEventDraft $draft, int $expectedRevision): void
    {
        $markedDraft = $this->markedDraft($draft);
        $currentEvent = $this->currentEventForEdit($appointmentEntity, $expectedRevision);
        $updatedEvent = $this->calendarGateway->updateEvent($currentEvent, $markedDraft);
        $appointmentEntity->refreshFromGoogle($updatedEvent, $this->clock->now());
        $appointmentEntity->getProject()->recordEvent('appointment_updated', $this->clock->now(), $this->translator->trans('appointment.changed_in_dossier', [
            '%oldTitle%' => $currentEvent->title,
            '%newTitle%' => $updatedEvent->title,
            '%oldDate%' => $this->formatWhen($currentEvent->startsAt),
            '%newDate%' => $this->formatWhen($updatedEvent->startsAt),
        ], 'commercial'));
        $this->entityManager->flush();
    }

    public function cancel(CommercialAppointmentEntity $appointmentEntity, int $expectedRevision): void
    {
        $currentEvent = $this->currentEventForEdit($appointmentEntity, $expectedRevision);
        $this->calendarGateway->deleteEvent($currentEvent);
        $appointmentEntity->cancel($this->clock->now());
        $appointmentEntity->getProject()->recordEvent('appointment_canceled', $this->clock->now(), $this->translator->trans('appointment.canceled_in_dossier', [
            '%title%' => $currentEvent->title,
            '%date%' => $this->formatWhen($currentEvent->startsAt),
        ], 'commercial'));
        $this->entityManager->flush();
    }

    public function setSummary(CommercialAppointmentEntity $appointmentEntity, ?string $summary, int $expectedRevision): void
    {
        $this->assertRevision($appointmentEntity, $expectedRevision);
        $appointmentEntity->setSummary($summary);
        $updated = $this->entityManager->getConnection()->executeStatement(
            'UPDATE commercial_appointment SET summary = :summary, revision = revision + 1 WHERE id = :id AND revision = :revision',
            ['summary' => $appointmentEntity->getSummary(), 'id' => $appointmentEntity->getId(), 'revision' => $expectedRevision],
        );
        $this->entityManager->refresh($appointmentEntity);
        if (0 === $updated) {
            throw new GoogleCalendarConflictException('The appointment revision is outdated.');
        }
    }

    private function currentEventForEdit(CommercialAppointmentEntity $appointmentEntity, int $expectedRevision): CalendarEventOccurrence
    {
        $this->assertRevision($appointmentEntity, $expectedRevision);
        if (CommercialAppointmentEntity::STATUS_CANCELED === $appointmentEntity->getStatus()) {
            throw new GoogleCalendarConflictException('The appointment was canceled.');
        }
        $currentEvent = $this->calendarGateway->event($appointmentEntity->getGoogleEventId());
        if (null === $currentEvent) {
            $appointmentEntity->cancel($this->clock->now());
            $this->entityManager->flush();
            throw new GoogleCalendarConflictException('The appointment was removed in Google Calendar.');
        }
        if ($currentEvent->etag !== $appointmentEntity->getEtag()) {
            $appointmentEntity->refreshFromGoogle($currentEvent, $this->clock->now());
            $this->entityManager->flush();
            throw new GoogleCalendarConflictException('The appointment changed in Google Calendar.');
        }

        return $currentEvent;
    }

    private function markedDraft(CalendarEventDraft $draft): CalendarEventDraft
    {
        $title = CommercialAppointmentTitle::withMarker($draft->title);
        if (255 < mb_strlen($title)) {
            throw new DomainException('The appointment title is too long after adding the Calendar marker.');
        }

        return new CalendarEventDraft($title, $draft->startsAt, $draft->endsAt, $draft->timeZone, $draft->location);
    }

    private function formatWhen(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i');
    }

    private function assertRevision(CommercialAppointmentEntity $appointmentEntity, int $expectedRevision): void
    {
        if ($appointmentEntity->getRevision() !== $expectedRevision) {
            throw new GoogleCalendarConflictException('The appointment revision is outdated.');
        }
    }
}
