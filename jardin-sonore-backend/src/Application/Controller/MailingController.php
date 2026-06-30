<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\CreateMailingCampaignType;
use App\Application\Form\EditMailingCampaignType;
use App\Application\Form\Model\CreateMailingCampaignFormModel;
use App\Application\Form\Model\EditMailingCampaignFormModel;
use App\Application\Mailing\CreateMailingCampaign;
use App\Application\Mailing\CreateMailingCampaignInput;
use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\ListMailingCampaigns;
use App\Application\Mailing\NewsletterAudienceOptionsProviderInterface;
use App\Application\Mailing\NewsletterAudienceResolverInterface;
use App\Application\Mailing\NewsletterRendererInterface;
use App\Application\Mailing\UpdateMailingCampaign;
use App\Application\Mailing\UpdateMailingCampaignInput;
use App\Domain\Model\Mailing\MailingRecommendation;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/mailing', name: 'mailing_')]
final class MailingController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ListMailingCampaigns $listMailingCampaigns): Response
    {
        return $this->render('mailing/index.html.twig', [
            'campaigns' => $listMailingCampaigns(),
        ]);
    }

    #[Route('/audience/municipalities/autocomplete', name: 'audience_municipalities_autocomplete', methods: ['GET'])]
    public function autocompleteMunicipalities(
        Request $request,
        NewsletterAudienceOptionsProviderInterface $newsletterAudienceOptionsProvider,
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $autocompleteChoices = $newsletterAudienceOptionsProvider->searchMunicipalityAutocompleteChoices(
            query: $request->query->getString('query'),
            page: $page,
            limit: $limit,
        );

        return $this->json([
            'results' => [
                'options' => $autocompleteChoices['results'],
                'optgroups' => $autocompleteChoices['optgroups'],
            ],
            'next_page' => $autocompleteChoices['has_next_page']
                ? $this->generateUrl('mailing_audience_municipalities_autocomplete', [
                    'query' => $request->query->getString('query'),
                    'page' => $page + 1,
                ])
                : null,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, CreateMailingCampaign $createMailingCampaign): Response
    {
        $formModel = new CreateMailingCampaignFormModel();
        $form = $this->createForm(CreateMailingCampaignType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mailingCampaign = $createMailingCampaign(new CreateMailingCampaignInput(
                internalTitle: $formModel->internalTitle,
                emailSubject: $formModel->emailSubject,
                publicTitle: $formModel->publicTitle,
                mainText: $formModel->mainText,
                subtitle: $formModel->subtitle,
                callToActionLabel: $formModel->callToActionLabel,
                callToActionUrl: $formModel->callToActionUrl,
                bannerImageFile: $formModel->bannerImageFile,
            ));

            $this->addFlash('success', 'mailing.flash.created');

            return $this->redirectToRoute('mailing_content', [
                'uuid' => $mailingCampaign->getUuid(),
            ]);
        }

        return $this->render(
            'mailing/new.html.twig',
            ['form' => $form->createView()],
            $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
    }

    #[Route('/{uuid}/content', name: 'content', methods: ['GET', 'POST'])]
    public function content(
        Uuid $uuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        UpdateMailingCampaign $updateMailingCampaign,
        NewsletterAudienceResolverInterface $newsletterAudienceResolver,
    ): Response {
        $mailingCampaign = $getMailingCampaign($uuid);

        if (null === $mailingCampaign) {
            throw $this->createNotFoundException();
        }

        $formModel = EditMailingCampaignFormModel::fromMailingCampaign($mailingCampaign);
        $form = $this->createForm(EditMailingCampaignType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updateMailingCampaign($mailingCampaign, new UpdateMailingCampaignInput(
                internalTitle: $formModel->internalTitle,
                emailSubject: $formModel->emailSubject,
                publicTitle: $formModel->publicTitle,
                mainText: $formModel->mainText,
                subtitle: $formModel->subtitle,
                callToActionLabel: $formModel->callToActionLabel,
                callToActionUrl: $formModel->callToActionUrl,
                bannerImageFile: $formModel->bannerImageFile,
                templateKey: $formModel->templateKey,
            ));

            $this->addFlash('success', 'mailing.flash.updated');

            return $this->redirectToRoute('mailing_content', [
                'uuid' => $mailingCampaign->getUuid(),
            ]);
        }

        $audienceRecipientCount = null;

        if ($mailingCampaign->getAudienceFilter()->hasActiveCriteria()) {
            try {
                $audienceRecipientCount = $newsletterAudienceResolver->resolve($mailingCampaign->getAudienceFilter(), 1)->getTotal();
            } catch (InvalidArgumentException) {
                $audienceRecipientCount = null;
            }
        }

        return $this->render(
            'mailing/content.html.twig',
            [
                'campaign' => $mailingCampaign,
                'hasAudienceCriteria' => $mailingCampaign->getAudienceFilter()->hasActiveCriteria(),
                'audienceRecipientCount' => $audienceRecipientCount,
                'form' => $form->createView(),
                'formSubmitted' => $form->isSubmitted(),
            ],
            $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
    }

    #[Route('/{uuid}/audience', name: 'audience', methods: ['GET'])]
    public function audience(
        Uuid $uuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
    ): Response {
        $mailingCampaign = $getMailingCampaign($uuid);

        if (null === $mailingCampaign) {
            throw $this->createNotFoundException();
        }

        return $this->render('mailing/audience.html.twig', [
            'campaign' => $mailingCampaign,
            'returnTo' => $request->query->getString('returnTo'),
        ]);
    }

    #[Route('/{uuid}/preview', name: 'preview', methods: ['GET'])]
    public function preview(
        Uuid $uuid,
        GetMailingCampaign $getMailingCampaign,
        NewsletterRendererInterface $newsletterRenderer,
    ): Response {
        $mailingCampaign = $getMailingCampaign($uuid);

        if (null === $mailingCampaign) {
            throw $this->createNotFoundException();
        }

        $activeRecommendationCount = count(array_filter(
            $mailingCampaign->getRecommendations(),
            static fn (MailingRecommendation $mailingRecommendation): bool => $mailingRecommendation->isActive(),
        ));

        return $this->render('mailing/preview.html.twig', [
            'campaign' => $mailingCampaign,
            'renderedNewsletter' => $newsletterRenderer->render($mailingCampaign),
            'activeRecommendationCount' => $activeRecommendationCount,
            'hasAudienceCriteria' => $mailingCampaign->getAudienceFilter()->hasActiveCriteria(),
        ]);
    }
}
