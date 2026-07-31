<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Backoffice\TableSort;
use App\Application\Form\MediaResourceType as MediaResourceFormType;
use App\Application\Form\Model\MediaResourceFormModel;
use App\Application\Form\Model\SessionSequenceFormModel;
use App\Application\Form\Model\SessionSummaryFormModel;
use App\Application\Form\RepertoireSessionSequenceType;
use App\Application\Form\SessionSequenceType as SessionSequenceFormType;
use App\Application\Form\SessionSummaryType as SessionSummaryFormType;
use App\Application\Session\AddSessionSequence;
use App\Application\Session\CreateMediaResource;
use App\Application\Session\CreateSessionSummary;
use App\Application\Session\DeleteSessionSummary;
use App\Application\Session\GetMediaResourceForEdit;
use App\Application\Session\GetRepertoireItemForEdit;
use App\Application\Session\GetSessionRecommendationForEdit;
use App\Application\Session\GetSessionSummary;
use App\Application\Session\MoveSessionSequence;
use App\Application\Session\RemoveSessionSequence;
use App\Application\Session\ReorderSessionSequences;
use App\Application\Session\SaveMediaResourceInput;
use App\Application\Session\SaveSessionSequenceInput;
use App\Application\Session\SaveSessionSummaryInput;
use App\Application\Session\SearchMediaResources;
use App\Application\Session\SearchRepertoireItems;
use App\Application\Session\SearchSessionRecommendations;
use App\Application\Session\SearchSessionSummaries;
use App\Application\Session\SessionSequenceLocalSetting;
use App\Application\Session\SessionSequenceView;
use App\Application\Session\SessionSummaryView;
use App\Application\Session\UpdateSessionSequence;
use App\Application\Session\UpdateSessionSequenceLocalSetting;
use App\Application\Session\UpdateSessionSequenceRole;
use App\Application\Session\UpdateSessionSummary;
use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Model\Session\SessionSequenceSourceKind;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
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
        $tableSort = TableSort::fromQuery(
            $request->query->getString('sort'),
            $request->query->getString('direction'),
            ['title', 'sessionDate', 'sequenceCount'],
            'sessionDate',
            'desc',
        );
        $sessions = $searchSessionSummaries($request->query->getString('query'));

        usort($sessions, static function (SessionSummaryView $left, SessionSummaryView $right) use ($tableSort): int {
            $leftValue = match ($tableSort->column) {
                'title' => $left->title,
                'sequenceCount' => count($left->sequences),
                default => $left->sessionDate->format('U'),
            };
            $rightValue = match ($tableSort->column) {
                'title' => $right->title,
                'sequenceCount' => count($right->sequences),
                default => $right->sessionDate->format('U'),
            };

            return $tableSort->compare($leftValue, $rightValue);
        });

        return $this->render('session/index.html.twig', [
            'query' => $request->query->getString('query'),
            'sessions' => $sessions,
            'tableSort' => $tableSort,
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

    #[Route('/{uuid}/remove', name: 'remove', methods: ['POST'])]
    public function remove(string $uuid, Request $request, DeleteSessionSummary $deleteSessionSummary): Response
    {
        if (!Uuid::isValid($uuid) || !$this->isCsrfTokenValid('session_remove_' . $uuid, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $deleteSessionSummary(Uuid::fromString($uuid));
        $this->addFlash('success', ['message' => 'sessions.summary.flash.removed', 'domain' => 'sessions']);

        return $this->redirectToRoute('session_index', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/{uuid}/document.pdf', name: 'document_download', methods: ['GET'])]
    public function downloadDocument(string $uuid, GetSessionSummary $getSessionSummary): Response
    {
        $sessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);
        if (null === $sessionSummaryView->documentPath || !is_file($sessionSummaryView->documentPath)) {
            throw $this->createNotFoundException();
        }

        return (new BinaryFileResponse($sessionSummaryView->documentPath))
            ->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'seance.pdf');
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

        $isRepertoireSessionConfiguration = '' !== $repertoireUuid && Uuid::isValid($repertoireUuid);

        if ($isRepertoireSessionConfiguration) {
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

        if ($openedFromComposer && !$isRepertoireSessionConfiguration && $request->isMethod('GET')) {
            $sessionSequence = $addSessionSequence($sessionSummaryView->uuid, $this->createSequenceInput($formModel));

            return $this->redirectToRoute('session_sequence_edit', [
                'uuid' => $sessionSummaryView->uuid->toRfc4122(),
                'sequenceUuid' => $sessionSequence->uuid->toRfc4122(),
                'composer' => 1,
                'draft' => 1,
            ], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(
            $isRepertoireSessionConfiguration ? RepertoireSessionSequenceType::class : SessionSequenceFormType::class,
            $formModel,
            $openedFromComposer && !$isRepertoireSessionConfiguration ? ['activity_only' => true] : [],
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sessionSequence = $addSessionSequence($sessionSummaryView->uuid, $this->createSequenceInput($formModel));

            if ($openedFromComposer) {
                return $this->render('session/composer_activity.stream.html.twig', [
                    'session' => $this->getSessionSummaryView($uuid, $getSessionSummary),
                    'sequenceUuid' => $sessionSequence->uuid->toRfc4122(),
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

        return $this->render(
            $openedFromComposer
                ? ($isRepertoireSessionConfiguration ? 'session/composer_repertoire_form.html.twig' : 'session/composer_activity_form.html.twig')
                : 'session/sequence_form.html.twig',
            [
                'form' => $form->createView(),
                'hasErrors' => $form->isSubmitted() && !$form->isValid(),
                'session' => $sessionSummaryView,
                'sequence' => null,
            ],
            $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
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
        $openedFromComposer = $request->query->getBoolean('composer');
        $isRepertoireSessionConfiguration = SessionSequenceSourceKind::REPERTOIRE_ITEM === $sequenceView->sourceKind;
        $form = $this->createForm(
            $isRepertoireSessionConfiguration ? RepertoireSessionSequenceType::class : SessionSequenceFormType::class,
            $formModel,
            $openedFromComposer && !$isRepertoireSessionConfiguration ? ['activity_only' => true] : [],
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formModel->body = $this->getSessionSequenceView(
                $this->getSessionSummaryView($uuid, $getSessionSummary),
                $sequenceUuid,
            )->body;
            $updateSessionSequence(
                $sessionSummaryView->uuid,
                Uuid::fromString($sequenceUuid),
                $this->createSequenceInput($formModel),
            );

            if ($openedFromComposer) {
                $updatedSessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);

                return $this->render('session/composer_activity.stream.html.twig', [
                    'session' => $updatedSessionSummaryView,
                    'summaryForm' => $this->createForm(
                        SessionSummaryFormType::class,
                        SessionSummaryFormModel::fromView($updatedSessionSummaryView),
                    )->createView(),
                ], new Response(headers: ['Content-Type' => 'text/vnd.turbo-stream.html']));
            }

            $this->addFlash('success', [
                'message' => 'sessions.sequence.flash.updated',
                'domain' => 'sessions',
            ]);

            return $this->redirectToRoute('session_edit', [
                'uuid' => $sessionSummaryView->uuid->toRfc4122(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render($openedFromComposer ? ($isRepertoireSessionConfiguration ? 'session/composer_repertoire_form.html.twig' : 'session/composer_activity_form.html.twig') : 'session/sequence_form.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => $form->isSubmitted() && !$form->isValid(),
            'session' => $sessionSummaryView,
            'sequence' => $sequenceView,
            'isDraft' => $request->query->getBoolean('draft'),
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{uuid}/sequences/{sequenceUuid}/remove', name: 'sequence_remove', methods: ['POST'])]
    public function removeSequence(
        string $uuid,
        string $sequenceUuid,
        Request $request,
        RemoveSessionSequence $removeSessionSequence,
        GetSessionSummary $getSessionSummary,
    ): Response {
        if (!Uuid::isValid($uuid) || !Uuid::isValid($sequenceUuid)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('session_sequence_remove_' . $sequenceUuid, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $removeSessionSequence(Uuid::fromString($uuid), Uuid::fromString($sequenceUuid));

        if (str_contains($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
            return $this->render('session/composer_activity.stream.html.twig', [
                'session' => $this->getSessionSummaryView($uuid, $getSessionSummary),
            ], new Response(headers: ['Content-Type' => 'text/vnd.turbo-stream.html']));
        }

        $this->addFlash('success', [
            'message' => 'sessions.sequence.flash.removed',
            'domain' => 'sessions',
        ]);

        return $this->redirectToRoute('session_edit', ['uuid' => $uuid], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{uuid}/sequences/{sequenceUuid}/media/picker', name: 'sequence_media_picker', methods: ['GET', 'POST'])]
    public function pickSequenceMedia(string $uuid, string $sequenceUuid, Request $request, GetSessionSummary $getSessionSummary, SearchMediaResources $searchMediaResources, UpdateSessionSequence $updateSessionSequence): Response
    {
        $sessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);
        $sequenceView = $this->getSessionSequenceView($sessionSummaryView, $sequenceUuid);

        if ('open-media-picker' === $request->request->getString('activityMediaAction')) {
            $sessionSequenceFormModel = SessionSequenceFormModel::fromView($sequenceView);
            $sessionSequenceForm = $this->createForm(SessionSequenceFormType::class, $sessionSequenceFormModel, ['activity_only' => true]);
            $sessionSequenceForm->handleRequest($request);

            if ($sessionSequenceForm->isSubmitted() && $sessionSequenceForm->isValid()) {
                $sessionSequenceFormModel->body = $sequenceView->body;
                $updateSessionSequence($sessionSummaryView->uuid, $sequenceView->uuid, $this->createSequenceInput($sessionSequenceFormModel));
                $sessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);
                $sequenceView = $this->getSessionSequenceView($sessionSummaryView, $sequenceUuid);
            } elseif (!$sessionSequenceForm->isSubmitted()) {
                return $this->render('session/composer_activity_form.html.twig', [
                    'form' => $sessionSequenceForm->createView(),
                    'hasErrors' => true,
                    'session' => $sessionSummaryView,
                    'sequence' => $sequenceView,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }
        }

        return $this->render('session/composer_activity_media_picker.html.twig', [
            'session' => $sessionSummaryView,
            'sequence' => $sequenceView,
            'isDraft' => $request->query->getBoolean('draft'),
            'mediaResources' => $searchMediaResources(query: $request->query->getString('query'), activeOnly: true),
            'query' => $request->query->getString('query'),
        ]);
    }

    #[Route('/{uuid}/sequences/{sequenceUuid}/media', name: 'sequence_media_add', methods: ['POST'])]
    public function addSequenceMedia(string $uuid, string $sequenceUuid, Request $request, GetSessionSummary $getSessionSummary, GetMediaResourceForEdit $getMediaResourceForEdit, UpdateSessionSequence $updateSessionSequence): Response
    {
        if (!$this->isCsrfTokenValid('session_sequence_media_add_' . $sequenceUuid, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $sessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);
        $sequenceView = $this->getSessionSequenceView($sessionSummaryView, $sequenceUuid);
        $mediaUuid = $request->request->getString('mediaUuid');
        $mediaResourceView = Uuid::isValid($mediaUuid) ? $getMediaResourceForEdit(Uuid::fromString($mediaUuid)) : null;
        if (null === $mediaResourceView || !$mediaResourceView->active) {
            throw $this->createNotFoundException();
        }
        $formModel = SessionSequenceFormModel::fromView($sequenceView);
        $formModel->addMediaResource($mediaResourceView);
        $updateSessionSequence($sessionSummaryView->uuid, $sequenceView->uuid, $this->createSequenceInput($formModel));

        $updatedSessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);
        $updatedSequenceView = $this->getSessionSequenceView($updatedSessionSummaryView, $sequenceUuid);
        $form = $this->createForm(SessionSequenceFormType::class, SessionSequenceFormModel::fromView($updatedSequenceView), ['activity_only' => true]);

        return $this->render('session/composer_activity_form.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => false,
            'session' => $updatedSessionSummaryView,
            'sequence' => $updatedSequenceView,
            'isDraft' => $request->query->getBoolean('draft'),
        ]);
    }

    #[Route('/{uuid}/sequences/{sequenceUuid}/media/new', name: 'sequence_media_create', methods: ['GET', 'POST'])]
    public function createSequenceMedia(string $uuid, string $sequenceUuid, Request $request, GetSessionSummary $getSessionSummary, CreateMediaResource $createMediaResource, UpdateSessionSequence $updateSessionSequence): Response
    {
        $sessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);
        $sequenceView = $this->getSessionSequenceView($sessionSummaryView, $sequenceUuid);

        if ('open-media-create' === $request->request->getString('activityMediaAction')) {
            $sessionSequenceFormModel = SessionSequenceFormModel::fromView($sequenceView);
            $sessionSequenceForm = $this->createForm(SessionSequenceFormType::class, $sessionSequenceFormModel, ['activity_only' => true]);
            $sessionSequenceForm->handleRequest($request);

            if (!$sessionSequenceForm->isSubmitted()) {
                return $this->render('session/composer_activity_form.html.twig', [
                    'form' => $sessionSequenceForm->createView(),
                    'hasErrors' => true,
                    'session' => $sessionSummaryView,
                    'sequence' => $sequenceView,
                    'isDraft' => $request->query->getBoolean('draft'),
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $sessionSequenceFormModel->body = $sequenceView->body;
            $updateSessionSequence($sessionSummaryView->uuid, $sequenceView->uuid, $this->createSequenceInput($sessionSequenceFormModel));
            $sessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);
            $sequenceView = $this->getSessionSequenceView($sessionSummaryView, $sequenceUuid);
        }

        $mediaResourceFormModel = new MediaResourceFormModel();
        $form = $this->createForm(MediaResourceFormType::class, $mediaResourceFormModel);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $mediaResource = $createMediaResource(new SaveMediaResourceInput($mediaResourceFormModel->type, $mediaResourceFormModel->title, $mediaResourceFormModel->primaryUrl, $mediaResourceFormModel->primaryFile, $mediaResourceFormModel->source, $mediaResourceFormModel->description, $mediaResourceFormModel->secondaryUrl, $mediaResourceFormModel->imageUrl, $mediaResourceFormModel->imageFile, $mediaResourceFormModel->themeUuids, $mediaResourceFormModel->active));
            $formModel = SessionSequenceFormModel::fromView($sequenceView);
            $formModel->addMediaResource(\App\Application\Session\MediaResourceView::fromDomain($mediaResource));
            $updateSessionSequence($sessionSummaryView->uuid, $sequenceView->uuid, $this->createSequenceInput($formModel));

            $updatedSessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);
            $updatedSequenceView = $this->getSessionSequenceView($updatedSessionSummaryView, $sequenceUuid);
            $sessionSequenceForm = $this->createForm(SessionSequenceFormType::class, SessionSequenceFormModel::fromView($updatedSequenceView), ['activity_only' => true]);

            return $this->render('session/composer_activity_form.html.twig', [
                'form' => $sessionSequenceForm->createView(),
                'hasErrors' => false,
                'session' => $updatedSessionSummaryView,
                'sequence' => $updatedSequenceView,
            ]);
        }

        return $this->render('session/composer_activity_media_create.html.twig', ['form' => $form->createView(), 'session' => $sessionSummaryView, 'sequence' => $sequenceView, 'hasErrors' => $form->isSubmitted() && !$form->isValid()], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
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

    #[Route('/{uuid}/sequences/{sequenceUuid}/local-setting', name: 'sequence_local_setting', methods: ['POST'])]
    public function updateSequenceLocalSetting(string $uuid, string $sequenceUuid, Request $request, UpdateSessionSequenceLocalSetting $updateSessionSequenceLocalSetting): Response
    {
        if (!Uuid::isValid($uuid) || !Uuid::isValid($sequenceUuid)) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('session_sequence_local_setting_' . $sequenceUuid, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $setting = SessionSequenceLocalSetting::tryFrom($request->request->getString('setting'));
        if (null === $setting) {
            throw $this->createNotFoundException();
        }

        $updateSessionSequenceLocalSetting(Uuid::fromString($uuid), Uuid::fromString($sequenceUuid), $setting, $request->request->get('value'));

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
        if (!in_array($catalog, ['repertoire', 'media'], true)) {
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
            };

            if (null === $formModel) {
                throw $this->createNotFoundException();
            }

            $sessionSequence = $addSessionSequence($sessionSummaryView->uuid, $this->createSequenceInput($formModel));

            if ('repertoire' === $catalog) {
                return $this->redirectToRoute('session_sequence_edit', [
                    'uuid' => $sessionSummaryView->uuid->toRfc4122(),
                    'sequenceUuid' => $sessionSequence->uuid->toRfc4122(),
                    'composer' => 1,
                    'draft' => 1,
                ], Response::HTTP_SEE_OTHER);
            }

            $updatedSessionSummaryView = $this->getSessionSummaryView($uuid, $getSessionSummary);

            return $this->render('session/composer_add.stream.html.twig', [
                'session' => $updatedSessionSummaryView,
            ], new Response(headers: ['Content-Type' => 'text/vnd.turbo-stream.html']));
        }

        $catalogItems = match ($catalog) {
            'repertoire' => $searchRepertoireItems(query: $request->query->getString('query'), activeOnly: true),
            'media' => $searchMediaResources(query: $request->query->getString('query'), activeOnly: true),
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
            recommendationUuids: $sessionSummaryFormModel->orderedRecommendationUuids(),
        );
    }

    private function createSequenceInput(SessionSequenceFormModel $sessionSequenceFormModel): SaveSessionSequenceInput
    {
        return new SaveSessionSequenceInput(
            type: $sessionSequenceFormModel->type,
            title: $sessionSequenceFormModel->title ?? '',
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
            media: array_map(static fn ($media): \App\Domain\Model\Session\SessionSequenceMedia => $media->toDomain(), $sessionSequenceFormModel->media),
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

    private function getSessionSequenceView(SessionSummaryView $sessionSummaryView, string $sequenceUuid): SessionSequenceView
    {
        if (!Uuid::isValid($sequenceUuid)) {
            throw $this->createNotFoundException();
        }
        foreach ($sessionSummaryView->sequences as $sessionSequenceView) {
            if ($sessionSequenceView->uuid->equals(Uuid::fromString($sequenceUuid))) {
                return $sessionSequenceView;
            }
        }
        throw $this->createNotFoundException();
    }
}
