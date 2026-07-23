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
use App\Application\Session\ReorderSessionSequences;
use App\Application\Session\SaveSessionSequenceInput;
use App\Application\Session\SaveSessionSummaryInput;
use App\Application\Session\SearchMediaResources;
use App\Application\Session\SearchRepertoireItems;
use App\Application\Session\SearchSessionRecommendations;
use App\Application\Session\SearchSessionSummaries;
use App\Application\Session\SessionSummaryView;
use App\Application\Session\UpdateSessionSequence;
use App\Application\Session\UpdateSessionSequenceRole;
use App\Application\Session\UpdateSessionSummary;
use App\Domain\Model\Session\MediaResourceType;
use DateTimeImmutable;
use InvalidArgumentException;
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
            $updateSessionSummary($sessionSummaryView->uuid, $this->createSummaryInput($formModel, $sessionSummaryView));
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
        $openedFromComposer = $request->query->getBoolean('composer');
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

            if ($openedFromComposer) {
                return $this->render('session/composer_activity.stream.html.twig', [
                    'session' => $this->getSessionSummaryView($uuid, $getSessionSummary),
                ], new Response(headers: ['Content-Type' => 'text/vnd.turbo-stream.html']));
            }

            $this->addFlash('success', [
                'message' => 'sessions.sequence.flash.created',
                'domain' => 'sessions',
            ]);

            return $this->redirectToRoute('session_edit', [
                'uuid' => $sessionSummaryView->uuid->toRfc4122(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render($openedFromComposer ? 'session/composer_activity_form.html.twig' : 'session/sequence_form.html.twig', [
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

    #[Route('/{uuid}/sequences/{sequenceUuid}/role', name: 'sequence_role', methods: ['POST'])]
    public function updateSequenceRole(
        string $uuid,
        string $sequenceUuid,
        Request $request,
        UpdateSessionSequenceRole $updateSessionSequenceRole,
    ): Response {
        if (!Uuid::isValid($uuid) || !Uuid::isValid($sequenceUuid)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('session_sequence_role_' . $sequenceUuid, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $updateSessionSequenceRole(
            Uuid::fromString($uuid),
            Uuid::fromString($sequenceUuid),
            $request->request->getString('role'),
        );

        return $this->redirectToRoute('session_edit', ['uuid' => $uuid], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{uuid}/sequences/reorder', name: 'sequence_reorder', methods: ['POST'], priority: 10)]
    public function reorderSequences(
        string $uuid,
        Request $request,
        ReorderSessionSequences $reorderSessionSequences,
    ): Response {
        if (!Uuid::isValid($uuid)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('session_sequence_reorder_' . $uuid, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $sequenceUuidStrings = $request->request->all('sequenceUuids');
        if (!array_all($sequenceUuidStrings, static fn (mixed $sequenceUuid): bool => is_string($sequenceUuid) && Uuid::isValid($sequenceUuid))) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $reorderSessionSequences(
                Uuid::fromString($uuid),
                array_map(static fn (string $sequenceUuid): Uuid => Uuid::fromString($sequenceUuid), $sequenceUuidStrings),
            );
        } catch (InvalidArgumentException) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/{uuid}/composer/add', name: 'composer_add', methods: ['GET', 'POST'])]
    public function composerAdd(
        string $uuid,
        Request $request,
        GetSessionSummary $getSessionSummary,
        SearchRepertoireItems $searchRepertoireItems,
        SearchMediaResources $searchMediaResources,
        SearchSessionRecommendations $searchSessionRecommendations,
        GetRepertoireItemForEdit $getRepertoireItemForEdit,
        GetMediaResourceForEdit $getMediaResourceForEdit,
        GetSessionRecommendationForEdit $getSessionRecommendationForEdit,
        AddSessionSequence $addSessionSequence,
    ): Response {
        $sessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);
        $catalog = $request->query->getString('catalog', 'repertoire');
        if (!in_array($catalog, ['repertoire', 'media', 'recommendation'], true)) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('session_sequence_add_' . $uuid, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $sourceUuid = $request->request->getString('sourceUuid');
            if (!Uuid::isValid($sourceUuid)) {
                throw $this->createNotFoundException();
            }

            $sourceUuidObject = Uuid::fromString($sourceUuid);
            $formModel = match ($catalog) {
                'repertoire' => ($repertoireItemView = $getRepertoireItemForEdit($sourceUuidObject))
                    ? SessionSequenceFormModel::fromRepertoireItemView($repertoireItemView)
                    : null,
                'media' => ($mediaResourceView = $getMediaResourceForEdit($sourceUuidObject))
                    ? SessionSequenceFormModel::fromMediaResourceView($mediaResourceView)
                    : null,
                'recommendation' => ($sessionRecommendationView = $getSessionRecommendationForEdit($sourceUuidObject))
                    ? SessionSequenceFormModel::fromSessionRecommendationView($sessionRecommendationView)
                    : null,
            };

            if (null === $formModel) {
                throw $this->createNotFoundException();
            }

            $addSessionSequence($sessionSummaryView->uuid, $this->createSequenceInput($formModel));
            $updatedSessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);

            return $this->render('session/composer_add.stream.html.twig', [
                'session' => $updatedSessionSummaryView,
            ], new Response(headers: ['Content-Type' => 'text/vnd.turbo-stream.html']));
        }

        $catalogItems = match ($catalog) {
            'repertoire' => $searchRepertoireItems(query: $request->query->getString('query'), activeOnly: true),
            'media' => $searchMediaResources(query: $request->query->getString('query'), activeOnly: true),
            'recommendation' => $searchSessionRecommendations(query: $request->query->getString('query'), activeOnly: true),
        };

        return $this->render('session/composer_add.html.twig', [
            'catalog' => $catalog,
            'catalogItems' => $catalogItems,
            'query' => $request->query->getString('query'),
            'session' => $sessionSummaryView,
        ]);
    }

    private function createSummaryInput(
        SessionSummaryFormModel $sessionSummaryFormModel,
        ?SessionSummaryView $existingSessionSummaryView = null,
    ): SaveSessionSummaryInput {
        return new SaveSessionSummaryInput(
            title: $sessionSummaryFormModel->title,
            sessionDate: $sessionSummaryFormModel->sessionDate ?? new DateTimeImmutable(),
            organizationName: null === $existingSessionSummaryView ? '' : $existingSessionSummaryView->organizationName,
            theme: $sessionSummaryFormModel->subtitle,
            generalNotes: $sessionSummaryFormModel->generalNotes,
            materialSummary: $existingSessionSummaryView?->materialSummary,
            furtherExploration: $existingSessionSummaryView?->furtherExploration,
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
            role: $sessionSequenceFormModel->role,
            sourceUuid: null !== $sessionSequenceFormModel->sourceUuid && Uuid::isValid($sessionSequenceFormModel->sourceUuid)
                ? Uuid::fromString($sessionSequenceFormModel->sourceUuid)
                : null,
            sourceKind: $sessionSequenceFormModel->sourceKind,
            sourceTitle: $sessionSequenceFormModel->sourceTitle,
            instrumentUuids: $sessionSequenceFormModel->instrumentUuids,
        );
    }

    private function getSessionSummaryView(string $uuid, GetSessionSummary $getSessionSummary): SessionSummaryView
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
