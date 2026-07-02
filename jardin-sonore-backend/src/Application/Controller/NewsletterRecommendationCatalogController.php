<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\Model\NewsletterRecommendationFormModel;
use App\Application\Form\NewsletterRecommendationType;
use App\Application\Mailing\AddNewsletterRecommendationToCampaign;
use App\Application\Mailing\CreateNewsletterRecommendation;
use App\Application\Mailing\CreateNewsletterRecommendationInput;
use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\SearchNewsletterRecommendations;
use App\Application\Mailing\UpdateNewsletterRecommendation;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterRecommendation;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/mailing/recommendations', name: 'mailing_recommendation_catalog_')]
final class NewsletterRecommendationCatalogController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        SearchNewsletterRecommendations $searchNewsletterRecommendations,
    ): Response {
        $query = $request->query->getString('query');

        return $this->render('mailing/recommendation_catalog/index.html.twig', [
            'query' => $query,
            'recommendations' => $searchNewsletterRecommendations($query),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        CreateNewsletterRecommendation $createNewsletterRecommendation,
        GetMailingCampaign $getMailingCampaign,
        AddNewsletterRecommendationToCampaign $addNewsletterRecommendationToCampaign,
    ): Response {
        $mailingCampaign = $this->resolveMailingCampaign($request, $getMailingCampaign);
        $formModel = new NewsletterRecommendationFormModel();
        $form = $this->createForm(NewsletterRecommendationType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newsletterRecommendation = $createNewsletterRecommendation($this->createInput($formModel));

            if ($mailingCampaign instanceof MailingCampaign) {
                $addNewsletterRecommendationToCampaign($mailingCampaign, $newsletterRecommendation);
                $this->addFlash('success', 'mailing.flash.recommendation_created_and_selected');

                return $this->redirectToRoute('mailing_campaign_recommendation_index', [
                    'campaignUuid' => $mailingCampaign->getUuid(),
                ], Response::HTTP_SEE_OTHER);
            }

            $this->addFlash('success', 'mailing.flash.recommendation_created');

            return $this->redirectToRoute('mailing_recommendation_catalog_index', status: Response::HTTP_SEE_OTHER);
        }

        $template = $mailingCampaign instanceof MailingCampaign
            ? 'mailing/recommendation_catalog/new_frame.html.twig'
            : 'mailing/recommendation_catalog/new.html.twig';

        return $this->render(
            $template,
            [
                'campaign' => $mailingCampaign,
                'form' => $form->createView(),
                'hasErrors' => $form->isSubmitted() && !$form->isValid(),
            ],
            $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
    }

    #[Route('/{uuid}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        string $uuid,
        Request $request,
        NewsletterRecommendationRepositoryInterface $newsletterRecommendationRepository,
        UpdateNewsletterRecommendation $updateNewsletterRecommendation,
    ): Response {
        $recommendation = Uuid::isValid($uuid)
            ? $newsletterRecommendationRepository->findByUuid(Uuid::fromString($uuid))
            : null;

        if (!$recommendation instanceof NewsletterRecommendation) {
            throw $this->createNotFoundException();
        }

        $formModel = NewsletterRecommendationFormModel::fromNewsletterRecommendation($recommendation);
        $form = $this->createForm(NewsletterRecommendationType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updateNewsletterRecommendation($recommendation, $this->createInput($formModel));
            $this->addFlash('success', 'mailing.flash.recommendation_updated');

            return $this->redirectToRoute('mailing_recommendation_catalog_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render(
            'mailing/recommendation_catalog/edit.html.twig',
            [
                'form' => $form->createView(),
                'hasErrors' => $form->isSubmitted() && !$form->isValid(),
                'recommendation' => $recommendation,
            ],
            $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
    }

    private function createInput(NewsletterRecommendationFormModel $formModel): CreateNewsletterRecommendationInput
    {
        return new CreateNewsletterRecommendationInput(
            title: $formModel->title,
            tag: $formModel->tag,
            text: $formModel->text,
            url: $formModel->url,
            linkLabel: $formModel->linkLabel,
            imageFile: $formModel->imageFile,
            active: $formModel->active,
        );
    }

    private function resolveMailingCampaign(
        Request $request,
        GetMailingCampaign $getMailingCampaign,
    ): ?MailingCampaign {
        $campaignUuid = $request->query->getString('campaign');

        if ('' === $campaignUuid) {
            return null;
        }

        if (!Uuid::isValid($campaignUuid)) {
            throw $this->createNotFoundException();
        }

        $mailingCampaign = $getMailingCampaign(Uuid::fromString($campaignUuid));

        if (!$mailingCampaign instanceof MailingCampaign) {
            throw $this->createNotFoundException();
        }

        return $mailingCampaign;
    }
}
