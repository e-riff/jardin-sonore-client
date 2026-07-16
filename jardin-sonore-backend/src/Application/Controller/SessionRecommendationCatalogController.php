<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\Model\SessionRecommendationFormModel;
use App\Application\Form\SessionRecommendationType as SessionRecommendationFormType;
use App\Application\Session\CreateSessionRecommendation;
use App\Application\Session\GetSessionRecommendationForEdit;
use App\Application\Session\SaveSessionRecommendationInput;
use App\Application\Session\SearchSessionRecommendations;
use App\Application\Session\UpdateSessionRecommendation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/sessions/recommendations', name: 'session_recommendation_')]
final class SessionRecommendationCatalogController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SearchSessionRecommendations $searchSessionRecommendations): Response
    {
        return $this->render('session_recommendation/index.html.twig', [
            'sessionQuery' => $request->query->getString('session'),
            'query' => $request->query->getString('query'),
            'items' => $searchSessionRecommendations($request->query->getString('query')),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, CreateSessionRecommendation $createSessionRecommendation): Response
    {
        $formModel = new SessionRecommendationFormModel();
        $form = $this->createForm(SessionRecommendationFormType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $createSessionRecommendation($this->createInput($formModel));
            $this->addFlash('success', ['message' => 'sessions.recommendation.flash.created', 'domain' => 'sessions']);

            return $this->redirectToRoute('session_recommendation_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('session_recommendation/form.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => $form->isSubmitted() && !$form->isValid(),
            'item' => null,
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{uuid}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        string $uuid,
        Request $request,
        GetSessionRecommendationForEdit $getSessionRecommendationForEdit,
        UpdateSessionRecommendation $updateSessionRecommendation,
    ): Response {
        $itemView = Uuid::isValid($uuid) ? $getSessionRecommendationForEdit(Uuid::fromString($uuid)) : null;

        if (null === $itemView) {
            throw $this->createNotFoundException();
        }

        $formModel = SessionRecommendationFormModel::fromView($itemView);
        $form = $this->createForm(SessionRecommendationFormType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updateSessionRecommendation($itemView->uuid, $this->createInput($formModel));
            $this->addFlash('success', ['message' => 'sessions.recommendation.flash.updated', 'domain' => 'sessions']);

            return $this->redirectToRoute('session_recommendation_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('session_recommendation/form.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => $form->isSubmitted() && !$form->isValid(),
            'item' => $itemView,
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    private function createInput(SessionRecommendationFormModel $formModel): SaveSessionRecommendationInput
    {
        return new SaveSessionRecommendationInput(
            title: $formModel->title,
            text: $formModel->text,
            notes: $formModel->notes,
            primaryUrl: $formModel->primaryUrl,
            secondaryUrl: $formModel->secondaryUrl,
            imageUrl: $formModel->imageUrl,
            imageFile: $formModel->imageFile,
            active: $formModel->active,
        );
    }
}
