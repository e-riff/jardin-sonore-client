<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\MailingRecommendationType;
use App\Application\Form\Model\MailingRecommendationFormModel;
use App\Application\Mailing\AddNewsletterRecommendationToCampaign;
use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\MoveMailingRecommendation;
use App\Application\Mailing\RemoveMailingRecommendation;
use App\Application\Mailing\SearchNewsletterRecommendations;
use App\Application\Mailing\UpdateMailingRecommendation;
use App\Application\Mailing\UpdateMailingRecommendationInput;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingRecommendation;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/mailing/{campaignUuid}/recommendations', name: 'mailing_campaign_recommendation')]
final class MailingCampaignRecommendationController extends AbstractController
{
    #[Route('', name: '_index', methods: ['GET'])]
    public function index(
        string $campaignUuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        SearchNewsletterRecommendations $searchNewsletterRecommendations,
    ): Response {
        $mailingCampaign = $this->getMailingCampaign($campaignUuid, $getMailingCampaign);
        $selectedSourceUuids = [];

        foreach ($mailingCampaign->getRecommendations() as $mailingRecommendation) {
            $sourceUuid = $mailingRecommendation->getSourceRecommendationUuid();

            if (null !== $sourceUuid) {
                $selectedSourceUuids[$sourceUuid->toRfc4122()] = true;
            }
        }

        $availableRecommendations = array_values(array_filter(
            $searchNewsletterRecommendations($request->query->getString('query'), true),
            static fn ($recommendation): bool => !isset($selectedSourceUuids[$recommendation->getUuid()->toRfc4122()]),
        ));

        return $this->render('mailing/recommendations/frame.html.twig', [
            'campaign' => $mailingCampaign,
            'availableRecommendations' => $availableRecommendations,
            'query' => $request->query->getString('query'),
        ]);
    }

    #[Route('/catalog/{catalogUuid}/select', name: '_select', methods: ['POST'])]
    public function select(
        string $campaignUuid,
        string $catalogUuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        NewsletterRecommendationRepositoryInterface $newsletterRecommendationRepository,
        AddNewsletterRecommendationToCampaign $addNewsletterRecommendationToCampaign,
    ): Response {
        $this->assertCsrfToken($request, "select-recommendation-{$catalogUuid}");
        $mailingCampaign = $this->getMailingCampaign($campaignUuid, $getMailingCampaign);
        $this->assertCampaignEditable($mailingCampaign);
        $newsletterRecommendation = Uuid::isValid($catalogUuid)
            ? $newsletterRecommendationRepository->findByUuid(Uuid::fromString($catalogUuid))
            : null;

        if (null === $newsletterRecommendation) {
            throw $this->createNotFoundException();
        }

        $addNewsletterRecommendationToCampaign($mailingCampaign, $newsletterRecommendation);

        return $this->redirectToRecommendations($mailingCampaign);
    }

    #[Route('/{recommendationUuid}/remove', name: '_remove', methods: ['POST'])]
    public function remove(
        string $campaignUuid,
        string $recommendationUuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        RemoveMailingRecommendation $removeMailingRecommendation,
    ): Response {
        $this->assertCsrfToken($request, "remove-recommendation-{$recommendationUuid}");
        $mailingCampaign = $this->getMailingCampaign($campaignUuid, $getMailingCampaign);
        $this->assertCampaignEditable($mailingCampaign);

        if (!Uuid::isValid($recommendationUuid)
            || !$removeMailingRecommendation($mailingCampaign, Uuid::fromString($recommendationUuid))) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRecommendations($mailingCampaign);
    }

    #[Route('/{recommendationUuid}/move/{direction}', name: '_move', methods: ['POST'])]
    public function move(
        string $campaignUuid,
        string $recommendationUuid,
        string $direction,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        MoveMailingRecommendation $moveMailingRecommendation,
    ): Response {
        $this->assertCsrfToken($request, "move-recommendation-{$recommendationUuid}");
        $mailingCampaign = $this->getMailingCampaign($campaignUuid, $getMailingCampaign);
        $this->assertCampaignEditable($mailingCampaign);
        $offset = 'up' === $direction ? -1 : ('down' === $direction ? 1 : 0);

        if (!Uuid::isValid($recommendationUuid)
            || !$moveMailingRecommendation($mailingCampaign, Uuid::fromString($recommendationUuid), $offset)) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRecommendations($mailingCampaign);
    }

    #[Route('/{recommendationUuid}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(
        string $campaignUuid,
        string $recommendationUuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        UpdateMailingRecommendation $updateMailingRecommendation,
    ): Response {
        $mailingCampaign = $this->getMailingCampaign($campaignUuid, $getMailingCampaign);
        $this->assertCampaignEditable($mailingCampaign);
        $mailingRecommendation = $this->findRecommendation($mailingCampaign, $recommendationUuid);
        $formModel = MailingRecommendationFormModel::fromMailingRecommendation($mailingRecommendation);
        $form = $this->createForm(MailingRecommendationType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updateMailingRecommendation(
                $mailingCampaign,
                $mailingRecommendation->getUuid(),
                new UpdateMailingRecommendationInput(
                    uuid: $mailingRecommendation->getUuid(),
                    title: $formModel->title,
                    text: $formModel->text,
                    url: $formModel->url,
                    linkLabel: $formModel->linkLabel,
                    active: $formModel->active,
                ),
            );

            return $this->redirectToRecommendations($mailingCampaign);
        }

        return $this->render(
            'mailing/recommendations/edit_frame.html.twig',
            [
                'campaign' => $mailingCampaign,
                'form' => $form->createView(),
                'recommendation' => $mailingRecommendation,
            ],
            $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
    }

    private function getMailingCampaign(
        string $uuid,
        GetMailingCampaign $getMailingCampaign,
    ): MailingCampaign {
        $mailingCampaign = Uuid::isValid($uuid)
            ? $getMailingCampaign(Uuid::fromString($uuid))
            : null;

        if (!$mailingCampaign instanceof MailingCampaign) {
            throw $this->createNotFoundException();
        }

        return $mailingCampaign;
    }

    private function findRecommendation(
        MailingCampaign $mailingCampaign,
        string $uuid,
    ): MailingRecommendation {
        if (Uuid::isValid($uuid)) {
            foreach ($mailingCampaign->getRecommendations() as $mailingRecommendation) {
                if ($mailingRecommendation->getUuid()->equals(Uuid::fromString($uuid))) {
                    return $mailingRecommendation;
                }
            }
        }

        throw $this->createNotFoundException();
    }

    private function assertCsrfToken(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function assertCampaignEditable(MailingCampaign $mailingCampaign): void
    {
        if ($mailingCampaign->isEditable()) {
            return;
        }

        throw $this->createAccessDeniedException();
    }

    private function redirectToRecommendations(MailingCampaign $mailingCampaign): Response
    {
        return $this->redirectToRoute('mailing_campaign_recommendation_index', [
            'campaignUuid' => $mailingCampaign->getUuid(),
        ], Response::HTTP_SEE_OTHER);
    }
}
