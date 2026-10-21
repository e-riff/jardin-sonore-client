<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Commercial\CalendarEventDraft;
use App\Application\Commercial\CommercialAppointmentManager;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Google\GoogleCalendarApiException;
use App\Infrastructure\Google\GoogleCalendarConflictException;
use App\Infrastructure\Google\GoogleCalendarOAuthException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commercial', name: 'commercial_appointment_')]
final class CommercialAppointmentController extends AbstractController
{
    #[Route('/projects/{projectId}/appointments', name: 'create', requirements: ['projectId' => '\d+'], methods: ['POST'])]
    public function create(int $projectId, Request $request, EntityManagerInterface $entityManager, CommercialAppointmentManager $manager): Response
    {
        if (!$this->isCsrfTokenValid("commercial_project_{$projectId}_appointment_create", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $projectEntity = $entityManager->find(CommercialProjectEntity::class, $projectId);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw $this->createNotFoundException();
        }
        try {
            $draft = $this->draftFromRequest($request);
            $manager->create($projectEntity, $draft, $request->request->getString('requestId'));
        } catch (DomainException) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
            return new Response(status: Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->redirectToRoute('commercial_project_show', ['id' => $projectId]);
    }

    #[Route('/appointments/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager, CommercialAppointmentManager $manager): Response
    {
        $appointmentEntity = $this->findAppointment($id, $entityManager);
        if (!$this->isCsrfTokenValid("commercial_appointment_{$id}_edit", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $manager->update($appointmentEntity, $this->draftFromRequest($request), $this->revisionFromRequest($request));
        } catch (GoogleCalendarConflictException) {
            $this->addFlash('warning', ['message' => 'appointment.conflict', 'domain' => 'commercial']);

            return $this->redirectToRoute('commercial_project_show', ['id' => $appointmentEntity->getProject()->getId()]);
        } catch (DomainException) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
            return new Response(status: Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->redirectToRoute('commercial_project_show', ['id' => $appointmentEntity->getProject()->getId()]);
    }

    #[Route('/appointments/{id}/cancel', name: 'cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, Request $request, EntityManagerInterface $entityManager, CommercialAppointmentManager $manager): Response
    {
        $appointmentEntity = $this->findAppointment($id, $entityManager);
        if (!$this->isCsrfTokenValid("commercial_appointment_{$id}_cancel", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $manager->cancel($appointmentEntity, $this->revisionFromRequest($request));
        } catch (GoogleCalendarConflictException) {
            $this->addFlash('warning', ['message' => 'appointment.conflict', 'domain' => 'commercial']);

            return $this->redirectToRoute('commercial_project_show', ['id' => $appointmentEntity->getProject()->getId()]);
        } catch (DomainException) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
            return new Response(status: Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->redirectToRoute('commercial_project_show', ['id' => $appointmentEntity->getProject()->getId()]);
    }

    #[Route('/appointments/{id}/summary', name: 'summary', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function summary(int $id, Request $request, EntityManagerInterface $entityManager, CommercialAppointmentManager $manager): Response
    {
        $appointmentEntity = $this->findAppointment($id, $entityManager);
        if (!$this->isCsrfTokenValid("commercial_appointment_{$id}_summary", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $manager->setSummary($appointmentEntity, $request->request->getString('summary'), $this->revisionFromRequest($request));
        } catch (GoogleCalendarConflictException) {
            $this->addFlash('warning', ['message' => 'appointment.conflict', 'domain' => 'commercial']);

            return $this->redirectToRoute('commercial_project_show', ['id' => $appointmentEntity->getProject()->getId()]);
        } catch (DomainException) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->redirectToRoute('commercial_project_show', ['id' => $appointmentEntity->getProject()->getId()]);
    }

    private function findAppointment(int $id, EntityManagerInterface $entityManager): CommercialAppointmentEntity
    {
        $appointmentEntity = $entityManager->find(CommercialAppointmentEntity::class, $id);
        if (!$appointmentEntity instanceof CommercialAppointmentEntity) {
            throw $this->createNotFoundException();
        }

        return $appointmentEntity;
    }

    private function revisionFromRequest(Request $request): int
    {
        $revision = $request->request->getString('revision');
        if (!ctype_digit($revision) || 0 === (int) $revision) {
            throw new DomainException('An appointment revision is required.');
        }

        return (int) $revision;
    }

    private function draftFromRequest(Request $request): CalendarEventDraft
    {
        $title = trim($request->request->getString('title'));
        $startsAtRaw = $request->request->getString('startsAt');
        $endsAtRaw = $request->request->getString('endsAt');
        $timeZone = new DateTimeZone('Europe/Paris');
        $startsAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $startsAtRaw, $timeZone);
        $endsAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $endsAtRaw, $timeZone);
        if (255 < mb_strlen($title)
            || !$startsAt instanceof DateTimeImmutable || $startsAt->format('Y-m-d\TH:i') !== $startsAtRaw
            || !$endsAt instanceof DateTimeImmutable || $endsAt->format('Y-m-d\TH:i') !== $endsAtRaw) {
            throw new DomainException('Invalid appointment details.');
        }
        $location = trim($request->request->getString('location'));
        if (1000 < mb_strlen($location)) {
            throw new DomainException('Appointment location is too long.');
        }

        return new CalendarEventDraft($title, $startsAt, $endsAt, 'Europe/Paris', '' === $location ? null : $location);
    }
}
