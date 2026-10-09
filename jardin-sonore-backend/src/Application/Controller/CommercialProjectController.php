<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Commercial\CommercialProjectWorkflow;
use App\Application\Form\CommercialNoteType;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commercial/projects', name: 'commercial_project_')]
final class CommercialProjectController extends AbstractController
{
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        EntityManagerInterface $entityManager,
        #[Autowire('%app.commercial.drive_root_url%')] string $driveRootUrl,
        #[Autowire('%app.commercial.drive_quotes_url%')] string $driveQuotesUrl,
        #[Autowire('%app.commercial.drive_signed_quotes_url%')] string $driveSignedQuotesUrl,
        #[Autowire('%app.commercial.drive_invoices_url%')] string $driveInvoicesUrl,
    ): Response {
        $projectEntity = $this->findProject($id, $entityManager);
        $form = $this->createForm(CommercialNoteType::class, [
            'person' => $projectEntity->getPrimaryContact(),
            'nextActionDueOn' => new DateTimeImmutable('+7 days'),
        ], ['people' => $this->currentPeople($projectEntity)]);

        return $this->render('commercial/project/show.html.twig', [
            'project' => $projectEntity,
            'noteForm' => $form,
            'driveLinks' => [
                'root' => $driveRootUrl,
                'quotes' => $driveQuotesUrl,
                'signedQuotes' => $driveSignedQuotesUrl,
                'invoices' => $driveInvoicesUrl,
            ],
        ]);
    }

    #[Route('/{id}/notes', name: 'note', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function note(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $projectEntity = $this->findProject($id, $entityManager);
        $form = $this->createForm(CommercialNoteType::class, null, ['people' => $this->currentPeople($projectEntity)]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('commercial/project/show.html.twig', ['project' => $projectEntity, 'noteForm' => $form], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
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
                'noteForm' => $form,
                'noteError' => 'note.due_required',
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $projectEntity->recordEvent('note', new DateTimeImmutable(), $content, [], $personEntity instanceof PersonEntity ? $personEntity : null);
        if ('' !== $actionTitle) {
            $projectEntity->addAction(new CommercialActionEntity($projectEntity, $actionTitle, $dueOn, null, $personEntity instanceof PersonEntity ? $personEntity : null));
            $projectEntity->recordEvent('action_created', new DateTimeImmutable(), $actionTitle);
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
