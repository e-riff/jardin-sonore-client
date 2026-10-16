<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Commercial\CommercialProjectWorkflow;
use App\Application\Commercial\CreateCommercialProject;
use App\Application\Commercial\DeleteCommercialProject;
use App\Application\Commercial\RefreshCommercialInterventions;
use App\Application\Form\CommercialNoteType;
use App\Application\Form\CommercialProjectCreateType;
use App\Application\Form\CommercialProjectOrganizationType;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialEventEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
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

#[Route('/commercial/projects', name: 'commercial_project_')]
final class CommercialProjectController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('commercial/project/index.html.twig');
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ClockInterface $clock, CreateCommercialProject $creator): Response
    {
        $postedData = $request->request->all('commercial_project_create');
        $selectedOrganizationId = filter_var($postedData['organization'] ?? null, FILTER_VALIDATE_INT);
        $selectedOrganizationEntity = false === $selectedOrganizationId ? null : $entityManager->find(OrganizationEntity::class, $selectedOrganizationId);
        $people = $selectedOrganizationEntity instanceof OrganizationEntity
            ? array_values(array_filter($selectedOrganizationEntity->getPeople()->toArray(), static fn (PersonEntity $personEntity): bool => $personEntity->isActive()))
            : [];
        $form = $this->createForm(CommercialProjectCreateType::class, [
            'initialStatus' => CommercialProjectEntity::STATUS_DISCUSSION,
            'addTask' => true,
            'taskTitle' => 'Répondre',
            'taskDueOn' => $clock->now()->setTimezone(new DateTimeZone('Europe/Paris'))->setTime(0, 0),
        ], ['people' => $people]);
        $form->handleRequest($request);
        $error = null;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $organizationEntity = $data['organization'] ?? null;
            $newOrganizationName = trim((string) ($data['newOrganizationName'] ?? ''));
            $personEntity = $data['person'] ?? null;
            $newPersonFirstName = trim((string) ($data['newPersonFirstName'] ?? ''));
            $newPersonLastName = trim((string) ($data['newPersonLastName'] ?? ''));
            $newPersonRole = trim((string) ($data['newPersonRole'] ?? ''));
            $addTask = true === ($data['addTask'] ?? false);
            $taskTitle = trim((string) ($data['taskTitle'] ?? ''));
            $taskDueOn = $data['taskDueOn'] ?? null;
            if (($organizationEntity instanceof OrganizationEntity) === ('' !== $newOrganizationName)) {
                $error = 'project.create.choose_organization';
            } elseif ($organizationEntity instanceof OrganizationEntity && !$organizationEntity->isActive()) {
                $error = 'project.create.organization_inactive';
            } elseif (('' === $newPersonFirstName) !== ('' === $newPersonLastName) || ('' !== $newPersonRole && '' === $newPersonFirstName)) {
                $error = 'project.create.person_incomplete';
            } elseif ($personEntity instanceof PersonEntity && ($personEntity->getOrganization() !== $organizationEntity || !$personEntity->isActive())) {
                $error = 'project.create.person_invalid';
            } elseif ($personEntity instanceof PersonEntity && '' !== $newPersonFirstName) {
                $error = 'project.create.person_conflict';
            } elseif ('' !== $newPersonFirstName && !$organizationEntity instanceof OrganizationEntity && '' === $newOrganizationName) {
                $error = 'project.create.choose_organization';
            } elseif ($addTask && ('' === $taskTitle || !$taskDueOn instanceof DateTimeImmutable)) {
                $error = 'project.create.task_incomplete';
            } else {
                if (!$organizationEntity instanceof OrganizationEntity) {
                    $organizationEntity = (new OrganizationEntity())->setName($newOrganizationName);
                    $entityManager->persist($organizationEntity);
                }
                if ('' !== $newPersonFirstName) {
                    $personEntity = (new PersonEntity())->setFirstName($newPersonFirstName)->setLastName($newPersonLastName)->setRole('' === $newPersonRole ? null : $newPersonRole);
                    $organizationEntity->addPerson($personEntity);
                    $entityManager->persist($personEntity);
                }
                $projectEntity = $creator->manual(
                    $organizationEntity,
                    $personEntity instanceof PersonEntity ? $personEntity : null,
                    (string) ($data['firstMessage'] ?? ''),
                    $data['title'] ?? null,
                    (string) ($data['initialStatus'] ?? ''),
                    $addTask ? $taskTitle : null,
                    $addTask && $taskDueOn instanceof DateTimeImmutable ? $taskDueOn : null,
                );
                $entityManager->persist($projectEntity);
                $entityManager->flush();

                return $this->redirectToRoute('commercial_project_show', ['id' => $projectEntity->getId()]);
            }
        }

        return $this->render('commercial/project/new.html.twig', [
            'form' => $form,
            'creationError' => $error,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        EntityManagerInterface $entityManager,
        RefreshCommercialInterventions $interventionRefresher,
        #[Autowire('%app.commercial.drive_root_url%')] string $driveRootUrl,
        #[Autowire('%app.commercial.drive_quotes_url%')] string $driveQuotesUrl,
        #[Autowire('%app.commercial.drive_signed_quotes_url%')] string $driveSignedQuotesUrl,
        #[Autowire('%app.commercial.drive_invoices_url%')] string $driveInvoicesUrl,
    ): Response {
        $projectEntity = $this->findProject($id, $entityManager);
        $interventionRefresher->run($id);
        $interventions = $entityManager->getRepository(CommercialInterventionEntity::class)->findBy(['project' => $projectEntity], ['startsAt' => 'ASC']);
        $form = $this->createForm(CommercialNoteType::class, [
            'person' => $projectEntity->getPrimaryContact(),
            'nextActionDueOn' => new DateTimeImmutable('+7 days'),
        ], ['people' => $this->currentPeople($projectEntity)]);

        return $this->render('commercial/project/show.html.twig', [
            'project' => $projectEntity,
            'interventions' => $interventions,
            'noteForm' => $form,
            'organizationForm' => null === $projectEntity->getOrganization() ? $this->createForm(CommercialProjectOrganizationType::class) : null,
            'driveLinks' => [
                'root' => $driveRootUrl,
                'quotes' => $driveQuotesUrl,
                'signedQuotes' => $driveSignedQuotesUrl,
                'invoices' => $driveInvoicesUrl,
            ],
        ]);
    }

    #[Route('/{id}/title', name: 'title_edit', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function editTitle(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid("commercial_project_{$id}_title_edit", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $title = trim($request->request->getString('title'));
        if ('' === $title || 255 < mb_strlen($title)) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $projectEntity = $this->findProject($id, $entityManager);
        $projectEntity->setTitle($title);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager, DeleteCommercialProject $projectDeleter): Response
    {
        if (!$this->isCsrfTokenValid("commercial_project_{$id}_delete", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $projectEntity = $this->findProject($id, $entityManager);
        $projectDeleter->delete($projectEntity);

        return $this->redirectToRoute('commercial_project_index');
    }

    #[Route('/{id}/organization', name: 'organization', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function organization(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $projectEntity = $this->findProject($id, $entityManager);
        if (null !== $projectEntity->getOrganization()) {
            return new Response(status: Response::HTTP_CONFLICT);
        }
        $form = $this->createForm(CommercialProjectOrganizationType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        /** @var array<string, mixed> $data */
        $data = $form->getData();
        $organizationEntity = $data['organization'] ?? null;
        $newOrganizationName = trim((string) ($data['newOrganizationName'] ?? ''));
        if (($organizationEntity instanceof OrganizationEntity) === ('' !== $newOrganizationName) || ($organizationEntity instanceof OrganizationEntity && !$organizationEntity->isActive())) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$organizationEntity instanceof OrganizationEntity) {
            $organizationEntity = (new OrganizationEntity())->setName($newOrganizationName);
            $entityManager->persist($organizationEntity);
        }
        $projectEntity->setOrganization($organizationEntity);
        $projectEntity->recordEvent('organization_linked', new DateTimeImmutable(), $organizationEntity->getName());
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/{id}/notes', name: 'note', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function note(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $projectEntity = $this->findProject($id, $entityManager);
        $form = $this->createForm(CommercialNoteType::class, null, ['people' => $this->currentPeople($projectEntity)]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('commercial/project/show.html.twig', [
                'project' => $projectEntity,
                'interventions' => $entityManager->getRepository(CommercialInterventionEntity::class)->findBy(['project' => $projectEntity], ['startsAt' => 'ASC']),
                'noteForm' => $form,
                'organizationForm' => null === $projectEntity->getOrganization() ? $this->createForm(CommercialProjectOrganizationType::class) : null,
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData();
        $content = trim((string) ($data['content'] ?? ''));
        $personEntity = $data['person'] ?? null;
        $actionTitle = trim((string) ($data['nextActionTitle'] ?? ''));
        $dueOn = $data['nextActionDueOn'] ?? null;
        if ('' !== $actionTitle && !$dueOn instanceof DateTimeImmutable) {
            return $this->render('commercial/project/show.html.twig', [
                'project' => $projectEntity,
                'interventions' => $entityManager->getRepository(CommercialInterventionEntity::class)->findBy(['project' => $projectEntity], ['startsAt' => 'ASC']),
                'noteForm' => $form,
                'noteError' => 'note.due_required',
                'organizationForm' => null === $projectEntity->getOrganization() ? $this->createForm(CommercialProjectOrganizationType::class) : null,
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $projectEntity->recordEvent('note', new DateTimeImmutable(), $content, [], $personEntity instanceof PersonEntity ? $personEntity : null);
        if ('' !== $actionTitle) {
            $actionEntity = new CommercialActionEntity($projectEntity, $actionTitle, $dueOn, null, $personEntity instanceof PersonEntity ? $personEntity : null);
            $projectEntity->addAction($actionEntity);
            $projectEntity->recordEvent('action_created', new DateTimeImmutable(), $actionTitle)->linkAction($actionEntity);
        }
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/{id}/actions', name: 'action_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createAction(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid("commercial_project_{$id}_action_create", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $projectEntity = $this->findProject($id, $entityManager);
        $title = trim($request->request->getString('title'));
        $dueOnRaw = $request->request->getString('dueOn');
        $dueOn = DateTimeImmutable::createFromFormat('!Y-m-d', $dueOnRaw);
        $personIdRaw = $request->request->getString('personId');
        if ('' !== $personIdRaw && !ctype_digit($personIdRaw)) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $personId = '' === $personIdRaw ? 0 : (int) $personIdRaw;
        $personEntity = 0 < $personId ? $entityManager->find(PersonEntity::class, $personId) : null;
        if (in_array($projectEntity->getStatus(), [CommercialProjectEntity::STATUS_COMPLETED, CommercialProjectEntity::STATUS_WITHOUT_RESULT], true)
            || '' === $title || 255 < mb_strlen($title) || !$dueOn instanceof DateTimeImmutable || $dueOn->format('Y-m-d') !== $dueOnRaw
            || (0 < $personId && (!$personEntity instanceof PersonEntity || !in_array($personEntity, $this->currentPeople($projectEntity), true)))) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $projectEntity->addAction(new CommercialActionEntity($projectEntity, $title, $dueOn, null, $personEntity instanceof PersonEntity ? $personEntity : null));
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/{id}/events', name: 'event_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createEvent(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid("commercial_project_{$id}_event_create", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $projectEntity = $this->findProject($id, $entityManager);
        $content = trim($request->request->getString('content'));
        $personId = $request->request->getInt('personId');
        $personEntity = 0 < $personId ? $entityManager->find(PersonEntity::class, $personId) : null;
        if ('' === $content || 10000 < mb_strlen($content)
            || (0 < $personId && (!$personEntity instanceof PersonEntity || !in_array($personEntity, $this->currentPeople($projectEntity), true)))) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $projectEntity->recordEvent('note', new DateTimeImmutable(), $content, [], $personEntity instanceof PersonEntity ? $personEntity : null);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/{id}/events/{eventId}/edit', name: 'event_edit', requirements: ['id' => '\d+', 'eventId' => '\d+'], methods: ['POST'])]
    public function editEvent(int $id, int $eventId, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid("commercial_project_{$id}_event_{$eventId}_edit", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $projectEntity = $this->findProject($id, $entityManager);
        $eventEntity = $entityManager->find(CommercialEventEntity::class, $eventId);
        if (!$eventEntity instanceof CommercialEventEntity || $eventEntity->getProject() !== $projectEntity) {
            throw $this->createNotFoundException();
        }
        $content = trim($request->request->getString('content'));
        $occurredOnRaw = $request->request->getString('occurredOn');
        $occurredOn = DateTimeImmutable::createFromFormat('!Y-m-d', $occurredOnRaw);
        if (!in_array($eventEntity->getType(), ['note', 'initial_message'], true)
            || null !== $eventEntity->getAction() || null !== $eventEntity->getQuote() || null !== $eventEntity->getInvoice()
            || '' === $content || 10000 < mb_strlen($content)
            || !$occurredOn instanceof DateTimeImmutable || $occurredOn->format('Y-m-d') !== $occurredOnRaw) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $eventEntity->revise($content, $eventEntity->getMetadata(), $occurredOn);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/{id}/events/{eventId}/delete', name: 'event_delete', requirements: ['id' => '\d+', 'eventId' => '\d+'], methods: ['POST'])]
    public function deleteEvent(int $id, int $eventId, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid("commercial_project_{$id}_event_{$eventId}_delete", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $projectEntity = $this->findProject($id, $entityManager);
        $eventEntity = $entityManager->find(CommercialEventEntity::class, $eventId);
        if (!$eventEntity instanceof CommercialEventEntity || $eventEntity->getProject() !== $projectEntity) {
            throw $this->createNotFoundException();
        }

        $entityManager->remove($eventEntity);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/{id}/status', name: 'status_manual', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function manualStatus(int $id, Request $request, EntityManagerInterface $entityManager, CommercialProjectWorkflow $workflow): Response
    {
        if (!$this->isCsrfTokenValid("commercial_project_status_{$id}_manual", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $projectEntity = $this->findProject($id, $entityManager);
        try {
            $workflow->changeStatusManually($projectEntity, $request->request->getString('status'));
        } catch (DomainException) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/{id}/status/{status}', name: 'status', requirements: ['id' => '\d+', 'status' => 'confirm|without_result|complete'], methods: ['POST'])]
    public function status(int $id, string $status, Request $request, EntityManagerInterface $entityManager, CommercialProjectWorkflow $workflow): Response
    {
        if (!$this->isCsrfTokenValid("commercial_project_status_{$id}_{$status}", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $projectEntity = $this->findProject($id, $entityManager);
        match ($status) {
            'confirm' => $workflow->confirm($projectEntity),
            'without_result' => $workflow->closeWithoutResult($projectEntity),
            'complete' => $workflow->complete($projectEntity),
            default => throw $this->createNotFoundException(),
        };
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    private function findProject(int $id, EntityManagerInterface $entityManager): CommercialProjectEntity
    {
        $projectEntity = $entityManager->find(CommercialProjectEntity::class, $id);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw $this->createNotFoundException();
        }

        return $projectEntity;
    }

    /** @return list<PersonEntity> */
    private function currentPeople(CommercialProjectEntity $projectEntity): array
    {
        $people = [];
        foreach ($projectEntity->getPeople() as $projectPersonEntity) {
            $personEntity = $projectPersonEntity->getPerson();
            if ($projectPersonEntity->isCurrent() && $personEntity->isActive()) {
                $people[] = $personEntity;
            }
        }

        return $people;
    }
}
