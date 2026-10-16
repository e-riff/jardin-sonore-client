<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Commercial\CalendarEventDraft;
use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialCalendarGateway;
use App\Application\Commercial\CommercialInterventionTitle;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Google\GoogleCalendarApiException;
use App\Infrastructure\Google\GoogleCalendarConflictException;
use App\Infrastructure\Google\GoogleCalendarOAuthClient;
use App\Infrastructure\Google\GoogleCalendarOAuthException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/commercial/projects/{projectId}/interventions', name: 'commercial_intervention_', requirements: ['projectId' => '\\d+'])]
final class CommercialInterventionController extends AbstractController
{
    #[Route('/new', name: 'new', methods: ['GET'])]
    public function new(int $projectId, EntityManagerInterface $entityManager): Response
    {
        $projectEntity = $entityManager->find(CommercialProjectEntity::class, $projectId);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw $this->createNotFoundException();
        }

        return $this->render('commercial/project/intervention_new.html.twig', [
            'project' => $projectEntity,
            'calendarCreateRequestId' => bin2hex(random_bytes(16)),
        ]);
    }

    #[Route('/{id}/confirm', name: 'confirm', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function confirm(
        int $projectId,
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CommercialCalendarGateway $calendarGateway,
        ClockInterface $clock,
        TranslatorInterface $translator,
    ): Response {
        $this->validateToken("commercial_intervention_{$id}_confirm", $request);
        $interventionEntity = $this->findIntervention($projectId, $id, $entityManager);
        try {
            $currentEvent = $this->eventSnapshot($interventionEntity);
            $confirmedDraft = new CalendarEventDraft(
                CommercialInterventionTitle::forOption($interventionEntity->getTitle(), false),
                $interventionEntity->getStartsAt(),
                $interventionEntity->getEndsAt(),
                $interventionEntity->getTimeZone(),
                $interventionEntity->getLocation(),
            );
            $updatedEvent = $confirmedDraft->title === $currentEvent->title ? $currentEvent : $calendarGateway->updateEvent($currentEvent, $confirmedDraft);
            $this->refreshIntervention($interventionEntity, $updatedEvent, $clock->now());
            $interventionEntity->confirm();
            $interventionEntity->getProject()?->recordEvent('note', $clock->now(), $translator->trans('calendar.sessions.confirmed_note', ['%title%' => $interventionEntity->getTitle()], 'commercial'));
            $entityManager->flush();
            $this->addFlash('success', ['message' => 'calendar.sessions.confirmed', 'domain' => 'commercial']);
        } catch (GoogleCalendarConflictException) {
            $interventionEntity->markSyncFailed('google_version_conflict');
            $entityManager->flush();
            $this->addFlash('warning', ['message' => 'calendar.sessions.conflict', 'domain' => 'commercial']);
        } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
            $interventionEntity->markSyncFailed('google_sync_failed');
            $entityManager->flush();
            $this->addFlash('error', ['message' => 'calendar.sessions.sync_warning', 'domain' => 'commercial']);
        }

        return $this->redirectToRoute('commercial_project_show', ['id' => $projectId]);
    }

    #[Route('/new', name: 'create', methods: ['POST'])]
    public function create(
        int $projectId,
        Request $request,
        EntityManagerInterface $entityManager,
        CommercialCalendarGateway $calendarGateway,
        GoogleCalendarOAuthClient $oauthClient,
        ClockInterface $clock,
        #[Autowire('%app.commercial.calendar_id%')] string $calendarId,
    ): Response {
        $this->validateToken("commercial_project_{$projectId}_intervention_create", $request);
        $projectEntity = $entityManager->find(CommercialProjectEntity::class, $projectId);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw $this->createNotFoundException();
        }

        try {
            $draft = $this->draftFromRequest($request);
            $status = $request->request->getString('status');
            if (!in_array($status, [CommercialInterventionEntity::STATUS_OPTION, CommercialInterventionEntity::STATUS_CONFIRMED], true)) {
                throw new DomainException('Invalid intervention status.');
            }
            if (null === $oauthClient->connection()) {
                throw new GoogleCalendarOAuthException('Google Calendar is not connected.');
            }

            $googleEvent = $calendarGateway->createEvent($this->draftForStatus($draft, CommercialInterventionEntity::STATUS_OPTION === $status), $request->request->getString('request_id'));
            $interventionEntity = $this->newIntervention($projectEntity, $calendarId, $googleEvent, $clock->now());
            if (CommercialInterventionEntity::STATUS_CONFIRMED === $status) {
                $interventionEntity->confirm();
            }
            $entityManager->persist($interventionEntity);
            $entityManager->flush();
            $this->addFlash('success', ['message' => 'calendar.sessions.created', 'domain' => 'commercial']);
        } catch (DomainException|GoogleCalendarApiException|GoogleCalendarOAuthException) {
            $this->addFlash('error', ['message' => 'calendar.sessions.save_error', 'domain' => 'commercial']);
        }

        return $this->redirectToRoute('commercial_project_show', ['id' => $projectId]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function edit(
        int $projectId,
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CommercialCalendarGateway $calendarGateway,
        ClockInterface $clock,
        TranslatorInterface $translator,
    ): Response {
        $this->validateToken("commercial_intervention_{$id}_edit", $request);
        $interventionEntity = $this->findIntervention($projectId, $id, $entityManager);

        try {
            $draft = $this->draftForStatus($this->draftFromRequest($request), CommercialInterventionEntity::STATUS_OPTION === $interventionEntity->getStatus());
            $currentEvent = $this->eventSnapshot($interventionEntity);
            $updatedEvent = $calendarGateway->updateEvent($currentEvent, $draft);
            $this->refreshIntervention($interventionEntity, $updatedEvent, $clock->now());
            $entityManager->flush();
            $this->addFlash('success', ['message' => 'calendar.sessions.updated', 'domain' => 'commercial']);
        } catch (GoogleCalendarConflictException) {
            try {
                $latestEvent = $calendarGateway->event($interventionEntity->getGoogleEventId());
                $this->refreshIntervention($interventionEntity, $latestEvent, $clock->now());
                $entityManager->flush();
                $this->addFlash('warning', ['message' => 'calendar.sessions.conflict', 'domain' => 'commercial']);
            } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
                $interventionEntity->markSyncFailed('google_version_conflict');
                $entityManager->flush();
                $this->addFlash('error', ['message' => 'calendar.sessions.sync_warning', 'domain' => 'commercial']);
            }
        } catch (DomainException) {
            $this->addFlash('error', ['message' => 'calendar.sessions.invalid_data', 'domain' => 'commercial']);
        } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
            $interventionEntity->markSyncFailed('google_sync_failed');
            $entityManager->flush();
            $this->addFlash('error', ['message' => 'calendar.sessions.sync_warning', 'domain' => 'commercial']);
        }

        return $this->redirectToRoute('commercial_project_show', ['id' => $projectId]);
    }

    #[Route('/{id}/cancel', name: 'cancel', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function cancel(
        int $projectId,
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CommercialCalendarGateway $calendarGateway,
        ClockInterface $clock,
        TranslatorInterface $translator,
    ): Response {
        $this->validateToken("commercial_intervention_{$id}_cancel", $request);
        $interventionEntity = $this->findIntervention($projectId, $id, $entityManager);

        try {
            $calendarGateway->deleteEvent($this->eventSnapshot($interventionEntity));
            $now = $clock->now();
            $interventionEntity->cancel($now);
            $interventionEntity->getProject()?->recordEvent('note', $now, $translator->trans('calendar.sessions.canceled_note', ['%title%' => $interventionEntity->getTitle()], 'commercial'));
            $entityManager->flush();
            $this->addFlash('success', ['message' => 'calendar.sessions.canceled', 'domain' => 'commercial']);
        } catch (GoogleCalendarConflictException) {
            $interventionEntity->markSyncFailed('google_version_conflict');
            $entityManager->flush();
            $this->addFlash('error', ['message' => 'calendar.sessions.conflict', 'domain' => 'commercial']);
        } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
            $interventionEntity->markSyncFailed('google_sync_failed');
            $entityManager->flush();
            $this->addFlash('error', ['message' => 'calendar.sessions.sync_warning', 'domain' => 'commercial']);
        }

        return $this->redirectToRoute('commercial_project_show', ['id' => $projectId]);
    }

    #[Route('/{id}/detach', name: 'detach', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function detach(int $projectId, int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid("commercial_intervention_{$id}_detach", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $projectEntity = $entityManager->find(CommercialProjectEntity::class, $projectId);
        $interventionEntity = $entityManager->find(CommercialInterventionEntity::class, $id);
        if (!$projectEntity instanceof CommercialProjectEntity || !$interventionEntity instanceof CommercialInterventionEntity || $interventionEntity->getProject() !== $projectEntity) {
            throw $this->createNotFoundException();
        }

        $interventionEntity->detach();
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $projectId]);
    }

    private function findIntervention(int $projectId, int $id, EntityManagerInterface $entityManager): CommercialInterventionEntity
    {
        $projectEntity = $entityManager->find(CommercialProjectEntity::class, $projectId);
        $interventionEntity = $entityManager->find(CommercialInterventionEntity::class, $id);
        if (!$projectEntity instanceof CommercialProjectEntity || !$interventionEntity instanceof CommercialInterventionEntity || $interventionEntity->getProject() !== $projectEntity) {
            throw $this->createNotFoundException();
        }

        return $interventionEntity;
    }

    private function draftFromRequest(Request $request): CalendarEventDraft
    {
        $timeZone = 'Europe/Paris';

        return new CalendarEventDraft(
            trim($request->request->getString('title')),
            $this->parseLocalDateTime($request->request->getString('starts_at'), $timeZone),
            $this->parseLocalDateTime($request->request->getString('ends_at'), $timeZone),
            $timeZone,
            '' === trim($request->request->getString('location')) ? null : trim($request->request->getString('location')),
        );
    }

    private function parseLocalDateTime(string $value, string $timeZone): DateTimeImmutable
    {
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, new DateTimeZone($timeZone));
        $errors = DateTimeImmutable::getLastErrors();
        if (false === $dateTime || (false !== $errors && (0 < $errors['warning_count'] || 0 < $errors['error_count'])) || $dateTime->format('Y-m-d\\TH:i') !== $value) {
            throw new DomainException('Invalid intervention date.');
        }

        return $dateTime;
    }

    private function eventSnapshot(CommercialInterventionEntity $interventionEntity): CalendarEventOccurrence
    {
        return new CalendarEventOccurrence(
            $interventionEntity->getGoogleEventId(),
            CommercialInterventionTitle::forOption($interventionEntity->getTitle(), CommercialInterventionEntity::STATUS_OPTION === $interventionEntity->getStatus()),
            $interventionEntity->getStartsAt(),
            $interventionEntity->getEndsAt(),
            $interventionEntity->getLocation(),
            false,
            $interventionEntity->getRecurringEventId(),
            $interventionEntity->getOriginalStartsAt(),
            $interventionEntity->getEtag(),
            $interventionEntity->getTimeZone(),
        );
    }

    private function newIntervention(CommercialProjectEntity $projectEntity, string $calendarId, CalendarEventOccurrence $event, DateTimeImmutable $syncedAt): CommercialInterventionEntity
    {
        return new CommercialInterventionEntity(
            $projectEntity,
            $calendarId,
            $event->id,
            CommercialInterventionTitle::withoutOptionMarker($event->title),
            $event->startsAt,
            $event->endsAt,
            $event->timeZone,
            $event->location,
            $event->etag,
            $syncedAt,
            $event->recurringEventId,
            $event->originalStartsAt,
        );
    }

    private function refreshIntervention(CommercialInterventionEntity $interventionEntity, CalendarEventOccurrence $event, DateTimeImmutable $syncedAt): void
    {
        $interventionEntity->refreshFromGoogle(CommercialInterventionTitle::withoutOptionMarker($event->title), $event->startsAt, $event->endsAt, $event->timeZone, $event->location, $event->etag, $syncedAt);
    }

    private function draftForStatus(CalendarEventDraft $draft, bool $isOption): CalendarEventDraft
    {
        return new CalendarEventDraft(
            CommercialInterventionTitle::forOption($draft->title, $isOption),
            $draft->startsAt,
            $draft->endsAt,
            $draft->timeZone,
            $draft->location,
        );
    }

    private function validateToken(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
