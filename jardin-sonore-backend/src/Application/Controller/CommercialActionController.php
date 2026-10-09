<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Commercial\CommercialActionWorkflow;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commercial/actions', name: 'commercial_action_')]
final class CommercialActionController extends AbstractController
{
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager, CommercialActionWorkflow $workflow): Response
    {
        if (!$this->isCsrfTokenValid("commercial_action_{$id}_edit", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $actionEntity = $entityManager->find(CommercialActionEntity::class, $id);
        if (!$actionEntity instanceof CommercialActionEntity) {
            throw $this->createNotFoundException();
        }
        $title = trim($request->request->getString('title'));
        $dueOnRaw = $request->request->getString('dueOn');
        $dueOn = DateTimeImmutable::createFromFormat('!Y-m-d', $dueOnRaw);
        $personId = $request->request->getInt('personId');
        $personEntity = null;
        if (0 < $personId) {
            $personEntity = $entityManager->find(PersonEntity::class, $personId);
            $linked = false;
            foreach ($actionEntity->getProject()->getPeople() as $projectPersonEntity) {
                if ($projectPersonEntity->isCurrent() && $projectPersonEntity->getPerson() === $personEntity) {
                    $linked = true;
                    break;
                }
            }
            if (!$personEntity instanceof PersonEntity || !$personEntity->isActive() || !$linked) {
                return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
        if ('' === $title || 255 < mb_strlen($title) || !$dueOn instanceof DateTimeImmutable || $dueOn->format('Y-m-d') !== $dueOnRaw) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $details = trim($request->request->getString('details'));
        $workflow->revise($actionEntity, $title, $dueOn, '' === $details ? null : $details, $personEntity);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $actionEntity->getProject()->getId()]);
    }

    #[Route('/{id}/{operation}', name: 'change', requirements: ['id' => '\d+', 'operation' => 'complete|postpone|cancel'], methods: ['POST'])]
    public function change(int $id, string $operation, Request $request, EntityManagerInterface $entityManager, CommercialActionWorkflow $workflow): Response
    {
        if (!$this->isCsrfTokenValid("commercial_action_{$id}_{$operation}", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $actionEntity = $entityManager->find(CommercialActionEntity::class, $id);
        if (!$actionEntity instanceof CommercialActionEntity) {
            throw $this->createNotFoundException();
        }

        if ('complete' === $operation) {
            $note = trim($request->request->getString('note'));
            $workflow->complete($actionEntity, '' === $note ? null : $note);
        } elseif ('cancel' === $operation) {
            $workflow->cancel($actionEntity);
        } else {
            $dueOn = DateTimeImmutable::createFromFormat('!Y-m-d', $request->request->getString('dueOn'));
            if (!$dueOn instanceof DateTimeImmutable) {
                return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $workflow->postpone($actionEntity, $dueOn);
        }
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $actionEntity->getProject()->getId()]);
    }
}
