<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialAppointmentTitle;
use App\Application\Commercial\CommercialCalendarGateway;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Google\GoogleCalendarApiException;
use App\Infrastructure\Google\GoogleCalendarOAuthClient;
use App\Infrastructure\Google\GoogleCalendarOAuthException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commercial/calendar', name: 'commercial_calendar_')]
final class CommercialCalendarController extends AbstractController
{
    private const string TIME_ZONE = 'Europe/Paris';

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        CommercialCalendarGateway $calendarGateway,
        GoogleCalendarOAuthClient $oauthClient,
        ClockInterface $clock,
        #[Autowire('%app.commercial.calendar_id%')] string $calendarId,
    ): Response {
        if (null === $oauthClient->connection()) {
            $this->addFlash('error', ['message' => 'calendar.connection.required', 'domain' => 'commercial']);

            return $this->redirectToRoute('commercial_calendar_settings');
        }

        $timeZone = new DateTimeZone(self::TIME_ZONE);
        $now = $clock->now()->setTimezone($timeZone);
        $anchorDate = $this->dateFromQuery($request, 'date', $now, $timeZone);
        $view = $request->query->getString('view', 'month');
        if (!in_array($view, ['month', 'week', 'year'], true)) {
            $view = 'month';
        }

        $academicYearStart = $this->academicYearStart($anchorDate);
        $summaryFrom = $this->dateFromQuery($request, 'summaryFrom', $academicYearStart, $timeZone);
        $summaryTo = $this->dateFromQuery($request, 'summaryTo', $academicYearStart->modify('+1 year'), $timeZone);
        if ($summaryFrom >= $summaryTo) {
            $summaryFrom = $academicYearStart;
            $summaryTo = $academicYearStart->modify('+1 year');
        }
        if ('year' === $view) {
            $rangeStart = $academicYearStart;
            $rangeEnd = $academicYearStart->modify('+1 year');
        } elseif ('week' === $view) {
            $rangeStart = $anchorDate->modify('monday this week')->setTime(0, 0);
            $rangeEnd = $rangeStart->modify('+7 days');
        } else {
            $monthStart = $anchorDate->modify('first day of this month')->setTime(0, 0);
            $offset = (int) $monthStart->format('N') - 1;
            $cellCount = (int) ceil(($offset + (int) $monthStart->format('t')) / 7) * 7;
            $rangeStart = $monthStart->modify('-' . $offset . ' days');
            $rangeEnd = $rangeStart->modify('+' . $cellCount . ' days');
        }

        $queryStart = $rangeStart < $summaryFrom ? $rangeStart : $summaryFrom;
        $queryEnd = $rangeEnd > $summaryTo ? $rangeEnd : $summaryTo;
        $calendarError = false;
        try {
            $events = $calendarGateway->eventsBetween($queryStart, $queryEnd);
        } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
            $events = [];
            $calendarError = true;
        }

        $eventIds = array_map(static fn (CalendarEventOccurrence $event): string => $event->id, $events);
        $linkedEntities = [] === $eventIds ? [] : $entityManager->getRepository(CommercialInterventionEntity::class)->findBy([
            'calendarId' => $calendarId,
            'googleEventId' => $eventIds,
        ]);
        $interventionsByEventId = [];
        foreach ($linkedEntities as $linkedEntity) {
            $interventionsByEventId[$linkedEntity->getGoogleEventId()] = $linkedEntity;
        }
        $linkedAppointments = [] === $eventIds ? [] : $entityManager->getRepository(CommercialAppointmentEntity::class)->findBy(['calendarId' => $calendarId, 'googleEventId' => $eventIds]);
        $appointmentsByEventId = [];
        foreach ($linkedAppointments as $appointmentEntity) {
            $appointmentsByEventId[$appointmentEntity->getGoogleEventId()] = $appointmentEntity;
        }

        $eventsByDay = [];
        $unlinkedCount = 0;
        $summary = ['total' => 0, 'confirmed' => 0, 'option' => 0, 'unlinked' => 0];
        foreach ($events as $event) {
            $intervention = $interventionsByEventId[$event->id] ?? null;
            $appointment = $appointmentsByEventId[$event->id] ?? null;
            $project = $appointment?->getProject() ?? $intervention?->getProject();
            $eventStartsAt = $event->startsAt->setTimezone($timeZone);
            $isAppointment = $appointment instanceof CommercialAppointmentEntity || CommercialAppointmentTitle::hasMarker($event->title);
            $isScheduledSession = !$event->allDay && !$isAppointment && CommercialInterventionEntity::STATUS_CANCELED !== $intervention?->getStatus();
            if (null === $project && !$event->allDay && !$isAppointment && $eventStartsAt >= $rangeStart && $eventStartsAt < $rangeEnd) {
                ++$unlinkedCount;
            }
            if ($isScheduledSession && $eventStartsAt >= $summaryFrom && $eventStartsAt < $summaryTo) {
                ++$summary['total'];
                if (null === $project) {
                    ++$summary['unlinked'];
                } elseif (CommercialInterventionEntity::STATUS_OPTION === $intervention->getStatus()) {
                    ++$summary['option'];
                } else {
                    ++$summary['confirmed'];
                }
            }
            $dayKey = $eventStartsAt->format('Y-m-d');
            $eventsByDay[$dayKey][] = [
                'event' => $event,
                'project' => $project,
                'status' => $intervention?->getStatus(),
                'appointment' => $isAppointment,
            ];
        }

        $months = [];
        $calendarDays = [];
        if ('year' === $view) {
            for ($monthOffset = 0; 12 > $monthOffset; ++$monthOffset) {
                $monthStart = $academicYearStart->modify('+' . $monthOffset . ' months');
                $months[] = [
                    'date' => $monthStart,
                    'weeks' => $this->monthWeeks($monthStart, $eventsByDay),
                ];
            }
        } elseif ('week' === $view) {
            $calendarDays = $this->daysFrom($rangeStart, 7, $eventsByDay);
        } else {
            $monthStart = $anchorDate->modify('first day of this month')->setTime(0, 0);
            $months[] = [
                'date' => $monthStart,
                'weeks' => $this->monthWeeks($monthStart, $eventsByDay),
            ];
        }

        return $this->render('commercial/calendar/index.html.twig', [
            'view' => $view,
            'anchorDate' => $anchorDate,
            'academicYearStart' => $academicYearStart,
            'calendarDays' => $calendarDays,
            'months' => $months,
            'unlinkedCount' => $unlinkedCount,
            'summary' => $summary,
            'summaryFrom' => $summaryFrom,
            'summaryTo' => $summaryTo,
            'calendarError' => $calendarError,
            'previousDate' => $this->navigateDate($view, $anchorDate, $academicYearStart, -1)->format('Y-m-d'),
            'nextDate' => $this->navigateDate($view, $anchorDate, $academicYearStart, 1)->format('Y-m-d'),
            'today' => $now->format('Y-m-d'),
        ]);
    }

    private function dateFromQuery(Request $request, string $key, DateTimeImmutable $fallback, DateTimeZone $timeZone): DateTimeImmutable
    {
        $value = $request->query->getString($key, $fallback->format('Y-m-d'));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timeZone);

        return false !== $date && $date->format('Y-m-d') === $value ? $date : $fallback->setTime(0, 0);
    }

    private function academicYearStart(DateTimeImmutable $date): DateTimeImmutable
    {
        $year = (int) $date->format('Y');
        if (9 > (int) $date->format('n')) {
            --$year;
        }

        return new DateTimeImmutable(sprintf('%04d-09-01', $year), new DateTimeZone(self::TIME_ZONE));
    }

    private function navigateDate(string $view, DateTimeImmutable $anchorDate, DateTimeImmutable $academicYearStart, int $direction): DateTimeImmutable
    {
        if ('year' === $view) {
            return $academicYearStart->modify(sprintf('%+d years', $direction));
        }
        if ('week' === $view) {
            return $anchorDate->modify(sprintf('%+d weeks', $direction));
        }

        return $anchorDate->modify('first day of this month')->modify(sprintf('%+d months', $direction));
    }

    /**
     * @param array<string, list<array{event: CalendarEventOccurrence, project: ?CommercialProjectEntity, status: ?string}>> $eventsByDay
     *
     * @return list<list<array{date: DateTimeImmutable, inMonth: bool, events: list<array{event: CalendarEventOccurrence, project: ?CommercialProjectEntity, status: ?string}>}>>
     */
    private function monthWeeks(DateTimeImmutable $monthStart, array $eventsByDay): array
    {
        $offset = (int) $monthStart->format('N') - 1;
        $daysInMonth = (int) $monthStart->format('t');
        $cellCount = (int) ceil(($offset + $daysInMonth) / 7) * 7;
        $gridStart = $monthStart->modify('-' . $offset . ' days');
        $days = $this->daysFrom($gridStart, $cellCount, $eventsByDay);
        foreach ($days as &$day) {
            $day['inMonth'] = $day['date']->format('Y-m') === $monthStart->format('Y-m');
        }
        unset($day);

        return array_chunk($days, 7);
    }

    /**
     * @param array<string, list<array{event: CalendarEventOccurrence, project: ?CommercialProjectEntity, status: ?string}>> $eventsByDay
     *
     * @return list<array{date: DateTimeImmutable, inMonth?: bool, events: list<array{event: CalendarEventOccurrence, project: ?CommercialProjectEntity, status: ?string}>}>
     */
    private function daysFrom(DateTimeImmutable $start, int $count, array $eventsByDay): array
    {
        $days = [];
        for ($offset = 0; $offset < $count; ++$offset) {
            $date = $start->modify('+' . $offset . ' days');
            $days[] = [
                'date' => $date,
                'events' => $eventsByDay[$date->format('Y-m-d')] ?? [],
            ];
        }

        return $days;
    }
}
