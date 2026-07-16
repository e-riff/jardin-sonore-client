<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\Model\SessionSequenceFormModel;
use App\Application\Form\Model\SessionSummaryFormModel;
use App\Application\Form\SessionSequenceType as SessionSequenceFormType;
use App\Application\Form\SessionSummaryType as SessionSummaryFormType;
use App\Application\Session\AddSessionSequence;
use App\Application\Session\CreateSessionSummary;
use App\Application\Session\GetMediaResourceForEdit;
use App\Application\Session\GetRepertoireItemForEdit;
use App\Application\Session\GetSessionRecommendationForEdit;
use App\Application\Session\GetSessionSummary;
use App\Application\Session\MoveSessionSequence;
use App\Application\Session\RemoveSessionSequence;
use App\Application\Session\SaveSessionSequenceInput;
use App\Application\Session\SaveSessionSummaryInput;
use App\Application\Session\SearchSessionSummaries;
use App\Application\Session\UpdateSessionSequence;
use App\Application\Session\UpdateSessionSummary;
use App\Domain\Model\Session\MediaResourceType;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/sessions', name: 'session_')]
final class SessionSummaryController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SearchSessionSummaries $searchSessionSummaries): Response
    {
        return $this->render('session/index.html.twig', [
            'query' => $request->query->getString('query'),
            'sessions' => $searchSessionSummaries($request->query->getString('query')),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, CreateSessionSummary $createSessionSummary): Response
    {
        $formModel = new SessionSummaryFormModel();
        $formModel->sessionDate = new DateTimeImmutable();
        $form = $this->createForm(SessionSummaryFormType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sessionSummary = $createSessionSummary($this->createSummaryInput($formModel));
            $this->addFlash('success', [
                'message' => 'sessions.summary.flash.created',
                'domain' => 'sessions',
            ]);

            return $this->redirectToRoute('session_edit', [
                'uuid' => $sessionSummary->getUuid()->toRfc4122(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('session/new.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => $form->isSubmitted() && !$form->isValid(),
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{uuid}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        string $uuid,
        Request $request,
        GetSessionSummary $getSessionSummary,
        UpdateSessionSummary $updateSessionSummary,
    ): Response {
        $sessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);
        $formModel = SessionSummaryFormModel::fromView($sessionSummaryView);
        $form = $this->createForm(SessionSummaryFormType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updateSessionSummary($sessionSummaryView->uuid, $this->createSummaryInput($formModel));
            $this->addFlash('success', [
                'message' => 'sessions.summary.flash.updated',
                'domain' => 'sessions',
            ]);

            return $this->redirectToRoute('session_edit', [
                'uuid' => $sessionSummaryView->uuid->toRfc4122(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('session/edit.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => $form->isSubmitted() && !$form->isValid(),
            'session' => $sessionSummaryView,
            'mediaTypes' => MediaResourceType::cases(),
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    public function show(string $uuid, GetSessionSummary $getSessionSummary): Response
    {
        return $this->render('session/show.html.twig', [
            'session' => $this->getSessionSummaryView($uuid, $getSessionSummary),
        ]);
    }

    #[Route('/{uuid}/sequences/new', name: 'sequence_new', methods: ['GET', 'POST'])]
    public function newSequence(
        string $uuid,
        Request $request,
        GetSessionSummary $getSessionSummary,
        GetRepertoireItemForEdit $getRepertoireItemForEdit,
        GetMediaResourceForEdit $getMediaResourceForEdit,
        GetSessionRecommendationForEdit $getSessionRecommendationForEdit,
        AddSessionSequence $addSessionSequence,
    ): Response {
        $sessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);
        $formModel = new SessionSequenceFormModel();

        $repertoireUuid = $request->query->getString('repertoire');
        $mediaUuid = $request->query->getString('media');
        $recommendationUuid = $request->query->getString('recommendation');

        if ('' !== $repertoireUuid && Uuid::isValid($repertoireUuid)) {
            $repertoireItemView = $getRepertoireItemForEdit(Uuid::fromString($repertoireUuid));
            if (null !== $repertoireItemView) {
                $formModel = SessionSequenceFormModel::fromRepertoireItemView($repertoireItemView);
            }
        } elseif ('' !== $mediaUuid && Uuid::isValid($mediaUuid)) {
            $mediaResourceView = $getMediaResourceForEdit(Uuid::fromString($mediaUuid));
            if (null !== $mediaResourceView) {
                $formModel = SessionSequenceFormModel::fromMediaResourceView($mediaResourceView);
            }
        } elseif ('' !== $recommendationUuid && Uuid::isValid($recommendationUuid)) {
            $sessionRecommendationView = $getSessionRecommendationForEdit(Uuid::fromString($recommendationUuid));
            if (null !== $sessionRecommendationView) {
                $formModel = SessionSequenceFormModel::fromSessionRecommendationView($sessionRecommendationView);
            }
        }

        $form = $this->createForm(SessionSequenceFormType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $addSessionSequence($sessionSummaryView->uuid, $this->createSequenceInput($formModel));
            $this->addFlash('success', [
                'message' => 'sessions.sequence.flash.created',
                'domain' => 'sessions',
            ]);

            return $this->redirectToRoute('session_edit', [
                'uuid' => $sessionSummaryView->uuid->toRfc4122(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('session/sequence_form.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => $form->isSubmitted() && !$form->isValid(),
            'session' => $sessionSummaryView,
            'sequence' => null,
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{uuid}/sequences/{sequenceUuid}/edit', name: 'sequence_edit', methods: ['GET', 'POST'])]
    public function editSequence(
        string $uuid,
        string $sequenceUuid,
        Request $request,
        GetSessionSummary $getSessionSummary,
        UpdateSessionSequence $updateSessionSequence,
    ): Response {
        $sessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);

        if (!Uuid::isValid($sequenceUuid)) {
            throw $this->createNotFoundException();
        }

        $sequenceView = null;

        foreach ($sessionSummaryView->sequences as $candidateSequenceView) {
            if ($candidateSequenceView->uuid->equals(Uuid::fromString($sequenceUuid))) {
                $sequenceView = $candidateSequenceView;
                break;
            }
        }

        if (null === $sequenceView) {
            throw $this->createNotFoundException();
        }

        $formModel = SessionSequenceFormModel::fromView($sequenceView);
        $form = $this->createForm(SessionSequenceFormType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updateSessionSequence(
                $sessionSummaryView->uuid,
                Uuid::fromString($sequenceUuid),
                $this->createSequenceInput($formModel),
            );
            $this->addFlash('success', [
                'message' => 'sessions.sequence.flash.updated',
                'domain' => 'sessions',
            ]);

            return $this->redirectToRoute('session_edit', [
                'uuid' => $sessionSummaryView->uuid->toRfc4122(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('session/sequence_form.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => $form->isSubmitted() && !$form->isValid(),
            'session' => $sessionSummaryView,
            'sequence' => $sequenceView,
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{uuid}/sequences/{sequenceUuid}/remove', name: 'sequence_remove', methods: ['POST'])]
    public function removeSequence(
        string $uuid,
        string $sequenceUuid,
        RemoveSessionSequence $removeSessionSequence,
    ): Response {
        if (!Uuid::isValid($uuid) || !Uuid::isValid($sequenceUuid)) {
            throw $this->createNotFoundException();
        }

        $removeSessionSequence(Uuid::fromString($uuid), Uuid::fromString($sequenceUuid));
        $this->addFlash('success', [
            'message' => 'sessions.sequence.flash.removed',
            'domain' => 'sessions',
        ]);

        return $this->redirectToRoute('session_edit', ['uuid' => $uuid], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{uuid}/sequences/{sequenceUuid}/move-up', name: 'sequence_move_up', methods: ['POST'])]
    public function moveSequenceUp(
        string $uuid,
        string $sequenceUuid,
        MoveSessionSequence $moveSessionSequence,
    ): Response {
        if (!Uuid::isValid($uuid) || !Uuid::isValid($sequenceUuid)) {
            throw $this->createNotFoundException();
        }

        $moveSessionSequence->up(Uuid::fromString($uuid), Uuid::fromString($sequenceUuid));

        return $this->redirectToRoute('session_edit', ['uuid' => $uuid], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{uuid}/sequences/{sequenceUuid}/move-down', name: 'sequence_move_down', methods: ['POST'])]
    public function moveSequenceDown(
        string $uuid,
        string $sequenceUuid,
        MoveSessionSequence $moveSessionSequence,
    ): Response {
        if (!Uuid::isValid($uuid) || !Uuid::isValid($sequenceUuid)) {
            throw $this->createNotFoundException();
        }

        $moveSessionSequence->down(Uuid::fromString($uuid), Uuid::fromString($sequenceUuid));

        return $this->redirectToRoute('session_edit', ['uuid' => $uuid], Response::HTTP_SEE_OTHER);
    }

    private function createSummaryInput(SessionSummaryFormModel $sessionSummaryFormModel): SaveSessionSummaryInput
    {
        return new SaveSessionSummaryInput(
            title: $sessionSummaryFormModel->title,
            sessionDate: $sessionSummaryFormModel->sessionDate ?? new DateTimeImmutable(),
            organizationName: $sessionSummaryFormModel->organizationName,
            theme: $sessionSummaryFormModel->theme,
            generalNotes: $sessionSummaryFormModel->generalNotes,
            materialSummary: $sessionSummaryFormModel->materialSummary,
            furtherExploration: $sessionSummaryFormModel->furtherExploration,
            instrumentUuids: $sessionSummaryFormModel->instrumentUuids,
        );
    }

    private function createSequenceInput(SessionSequenceFormModel $sessionSequenceFormModel): SaveSessionSequenceInput
    {
        return new SaveSessionSequenceInput(
            type: $sessionSequenceFormModel->type,
            title: $sessionSequenceFormModel->title,
            subtitle: $sessionSequenceFormModel->subtitle,
            body: $sessionSequenceFormModel->body,
            lyrics: $sessionSequenceFormModel->lyrics,
            gestures: $sessionSequenceFormModel->gestures,
            notes: $sessionSequenceFormModel->notes,
            primaryUrl: $sessionSequenceFormModel->primaryUrl,
            secondaryUrl: $sessionSequenceFormModel->secondaryUrl,
            imageUrl: $sessionSequenceFormModel->imageUrl,
            showLyricsByDefault: $sessionSequenceFormModel->showLyricsByDefault,
            sourceUuid: null !== $sessionSequenceFormModel->sourceUuid && Uuid::isValid($sessionSequenceFormModel->sourceUuid)
                ? Uuid::fromString($sessionSequenceFormModel->sourceUuid)
                : null,
            sourceKind: $sessionSequenceFormModel->sourceKind,
            sourceTitle: $sessionSequenceFormModel->sourceTitle,
        );
    }

    private function getSessionSummaryView(string $uuid, GetSessionSummary $getSessionSummary): \App\Application\Session\SessionSummaryView
    {
        if (!Uuid::isValid($uuid)) {
            throw $this->createNotFoundException();
        }

        $sessionSummaryView = $getSessionSummary(Uuid::fromString($uuid));

        if (null === $sessionSummaryView) {
            throw $this->createNotFoundException();
        }

        return $sessionSummaryView;
    }
}
