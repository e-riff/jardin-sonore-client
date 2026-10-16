<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Commercial\CalendarEventDraft;
use App\Application\Commercial\CommercialCalendarGateway;
use App\Application\Commercial\CommercialInterventionTitle;
use App\Application\Commercial\GroupCalendarEvents;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Google\GoogleCalendarApiException;
use App\Infrastructure\Google\GoogleCalendarConflictException;
use App\Infrastructure\Google\GoogleCalendarOAuthClient;
use App\Infrastructure\Google\GoogleCalendarOAuthException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commercial/calendar', name: 'commercial_calendar_')]
final class CommercialCalendarInboxController extends AbstractController
{
    private const string TIME_ZONE = 'Europe/Paris';

    #[Route('/unlinked', name: 'unlinked', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        CommercialCalendarGateway $calendarGateway,
        GroupCalendarEvents $eventGrouper,
        GoogleCalendarOAuthClient $oauthClient,
        ClockInterface $clock,
        #[Autowire('%app.commercial.calendar_id%')] string $calendarId,
    ): Response {
        if (null === $oauthClient->connection()) {
            $this->addFlash('error', ['message' => 'calendar.connection.required', 'domain' => 'commercial']);

            return $this->redirectToRoute('commercial_calendar_settings');
        }

        [$defaultStart, $defaultEnd] = $this->defaultSchoolYearPeriod($clock);
        $startsAt = $this->dateFromQuery($request, 'from', $defaultStart, false);
        $endsAt = $this->dateFromQuery($request, 'to', $defaultEnd, true);
        if ($endsAt <= $startsAt || 367 < $startsAt->diff($endsAt)->days) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $events = $calendarGateway->eventsBetween($startsAt, $endsAt);
        } catch (GoogleCalendarApiException) {
            return $this->render('commercial/calendar/unlinked.html.twig', [
                'groups' => [],
                'projects' => [],
                'from' => $startsAt->format('Y-m-d'),
                'to' => $endsAt->format('Y-m-d'),
                'calendarError' => true,
            ]);
        }

        $eventIds = array_map(static fn ($event): string => $event->id, $events);
        $linkedEntities = [] === $eventIds ? [] : $entityManager->getRepository(CommercialInterventionEntity::class)->findBy([
            'calendarId' => $calendarId,
            'googleEventId' => $eventIds,
        ]);
        $linkedIds = [];
        foreach ($linkedEntities as $linkedEntity) {
            if (null !== $linkedEntity->getProject()) {
                $linkedIds[$linkedEntity->getGoogleEventId()] = true;
            }
        }
        $unlinkedEvents = array_values(array_filter($events, static fn ($event): bool => !isset($linkedIds[$event->id])));
        $projectEntities = $entityManager->getRepository(CommercialProjectEntity::class)->findBy(
            ['status' => [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED, CommercialProjectEntity::STATUS_COMPLETED]],
            ['createdAt' => 'DESC'],
        );

        return $this->render('commercial/calendar/unlinked.html.twig', [
            'groups' => $eventGrouper->group($unlinkedEvents),
            'pendingCount' => count(array_filter($unlinkedEvents, static fn ($event): bool => !$event->allDay)),
            'projects' => $projectEntities,
            'from' => $startsAt->format('Y-m-d'),
            'to' => $endsAt->format('Y-m-d'),
            'calendarError' => false,
        ]);
    }

    #[Route('/unlinked/attach', name: 'attach', methods: ['POST'])]
    public function attach(
        Request $request,
        EntityManagerInterface $entityManager,
        CommercialCalendarGateway $calendarGateway,
        GoogleCalendarOAuthClient $oauthClient,
        ClockInterface $clock,
        #[Autowire('%app.commercial.calendar_id%')] string $calendarId,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('commercial_calendar_attach', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (null === $oauthClient->connection()) {
            $this->addFlash('error', ['message' => 'calendar.connection.required', 'domain' => 'commercial']);

            return $this->redirectToRoute('commercial_calendar_settings');
        }

        $eventIds = array_values(array_unique(array_filter($request->request->all('event_ids'), static fn (mixed $id): bool => is_string($id) && '' !== $id)));
        $projectId = filter_var($request->request->get('project_id'), FILTER_VALIDATE_INT);
        $status = $request->request->getString('status');
        if ([] === $eventIds || false === $projectId || !in_array($status, [CommercialInterventionEntity::STATUS_OPTION, CommercialInterventionEntity::STATUS_CONFIRMED], true)) {
            $this->addFlash('error', ['message' => 'calendar.unlinked.invalid_selection', 'domain' => 'commercial']);

            return $this->redirectToUnlinked($request);
        }

        $projectEntity = $entityManager->find(CommercialProjectEntity::class, $projectId);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw $this->createNotFoundException();
        }

        [$defaultStart, $defaultEnd] = $this->defaultSchoolYearPeriod($clock);
        $startsAt = $this->dateFromQuery($request, 'from', $defaultStart, false);
        $endsAt = $this->dateFromQuery($request, 'to', $defaultEnd, true);
        try {
            $candidateEvents = $calendarGateway->eventsBetween($startsAt, $endsAt);
        } catch (GoogleCalendarApiException) {
            $this->addFlash('error', ['message' => 'calendar.unlinked.load_error', 'domain' => 'commercial']);

            return $this->redirectToUnlinked($request);
        }
        $candidatesById = [];
        foreach ($candidateEvents as $candidateEvent) {
            $candidatesById[$candidateEvent->id] = $candidateEvent;
        }

        $eventsToAttach = [];
        foreach ($eventIds as $eventId) {
            $candidateEvent = $candidatesById[$eventId] ?? null;
            if (null === $candidateEvent || $candidateEvent->allDay) {
                $this->addFlash('error', ['message' => 'calendar.unlinked.invalid_selection', 'domain' => 'commercial']);

                return $this->redirectToUnlinked($request);
            }
            $existingEntity = $entityManager->getRepository(CommercialInterventionEntity::class)->findOneBy(['calendarId' => $calendarId, 'googleEventId' => $eventId]);
            if ($existingEntity instanceof CommercialInterventionEntity && null !== $existingEntity->getProject()) {
                $this->addFlash('error', ['message' => 'calendar.unlinked.already_linked', 'domain' => 'commercial']);

                return $this->redirectToUnlinked($request);
            }
            $eventsToAttach[] = [$candidateEvent, $existingEntity];
        }

        try {
            foreach ($eventsToAttach as [$candidateEvent, $existingEntity]) {
                $eventTitle = CommercialInterventionTitle::forOption($candidateEvent->title, CommercialInterventionEntity::STATUS_OPTION === $status);
                $eventForStatus = $eventTitle === $candidateEvent->title ? $candidateEvent : $calendarGateway->updateEvent(
                    $candidateEvent,
                    new CalendarEventDraft($eventTitle, $candidateEvent->startsAt, $candidateEvent->endsAt, $candidateEvent->timeZone, $candidateEvent->location),
                );
                $cleanTitle = CommercialInterventionTitle::withoutOptionMarker($eventForStatus->title);
                $interventionEntity = $existingEntity instanceof CommercialInterventionEntity
                    ? $existingEntity
                    : new CommercialInterventionEntity(
                        $projectEntity,
                        $calendarId,
                        $eventForStatus->id,
                        $cleanTitle,
                        $eventForStatus->startsAt,
                        $eventForStatus->endsAt,
                        $eventForStatus->timeZone,
                        $eventForStatus->location,
                        $eventForStatus->etag,
                        $clock->now(),
                        $eventForStatus->recurringEventId,
                        $eventForStatus->originalStartsAt,
                    );
                if ($existingEntity instanceof CommercialInterventionEntity) {
                    $interventionEntity->refreshFromGoogle($cleanTitle, $eventForStatus->startsAt, $eventForStatus->endsAt, $eventForStatus->timeZone, $eventForStatus->location, $eventForStatus->etag, $clock->now());
                    $interventionEntity->assignTo($projectEntity);
                }
                $interventionEntity->setStatus($status);
                $entityManager->persist($interventionEntity);
            }
        } catch (GoogleCalendarConflictException) {
            $this->addFlash('warning', ['message' => 'calendar.sessions.conflict', 'domain' => 'commercial']);

            return $this->redirectToUnlinked($request);
        } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
            $this->addFlash('error', ['message' => 'calendar.sessions.sync_warning', 'domain' => 'commercial']);

            return $this->redirectToUnlinked($request);
        }
        $entityManager->flush();
        $this->addFlash('success', ['message' => 'calendar.unlinked.success', 'domain' => 'commercial']);

        return $this->redirectToUnlinked($request);
    }

    private function dateFromQuery(Request $request, string $key, string $default, bool $endOfDay): DateTimeImmutable
    {
        $input = $request->isMethod('POST') ? $request->request : $request->query;
        $value = $input->getString($key, $default);
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(self::TIME_ZONE));
        if (false === $dateTime || $dateTime->format('Y-m-d') !== $value) {
            $dateTime = new DateTimeImmutable($default, new DateTimeZone(self::TIME_ZONE));
        }

        return $endOfDay ? $dateTime->modify('+1 day') : $dateTime;
    }

    private function redirectToUnlinked(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('commercial_calendar_unlinked', [
            'from' => $request->request->getString('from'),
            'to' => $request->request->getString('to'),
        ]);
    }

    /**
     * @return array{string, string}
     */
    private function defaultSchoolYearPeriod(ClockInterface $clock): array
    {
        $today = $clock->now()->setTimezone(new DateTimeZone(self::TIME_ZONE));
        $year = (int) $today->format('Y');
        if (9 > (int) $today->format('n')) {
            --$year;
        }

        return [sprintf('%04d-09-01', $year), sprintf('%04d-09-01', $year + 1)];
    }
}
