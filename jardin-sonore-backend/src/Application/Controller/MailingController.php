<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\CreateMailingCampaignType;
use App\Application\Form\EditMailingCampaignType;
use App\Application\Form\MailingAudienceMaskType;
use App\Application\Form\MailingTestType;
use App\Application\Form\Model\CreateMailingCampaignFormModel;
use App\Application\Form\Model\EditMailingCampaignFormModel;
use App\Application\Form\Model\MailingAudienceMaskFormModel;
use App\Application\Form\Model\MailingTestFormModel;
use App\Application\Mailing\CreateMailingCampaign;
use App\Application\Mailing\CreateMailingCampaignInput;
use App\Application\Mailing\DeleteMailingCampaign;
use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\NewsletterAudienceMapQueryInterface;
use App\Application\Mailing\ListMailingAudienceMasks;
use App\Application\Mailing\ListMailingCampaigns;
use App\Application\Mailing\NewsletterAudienceOptionsQueryInterface;
use App\Application\Mailing\NewsletterAudienceResolverInterface;
use App\Application\Mailing\NewsletterRendererInterface;
use App\Application\Mailing\SendMailingCampaign;
use App\Application\Mailing\SendMailingCampaignTest;
use App\Application\Mailing\StopMailingCampaignDelivery;
use App\Application\Mailing\UpdateMailingCampaign;
use App\Application\Mailing\UpdateMailingCampaignInput;
use App\Domain\Model\Mailing\MailingRecommendation;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

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
        NewsletterAudienceOptionsQueryInterface $newsletterAudienceOptionsQuery,
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $autocompleteChoices = $newsletterAudienceOptionsQuery->searchMunicipalityAutocompleteChoices(
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

    #[Route('/audience/municipalities/in-polygon', name: 'audience_municipalities_in_polygon', methods: ['POST'])]
    public function municipalitiesInPolygon(
        Request $request,
        NewsletterAudienceMapQueryInterface $newsletterAudienceMapQuery,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $polygonPoints = is_array($payload['points'] ?? null) ? $payload['points'] : [];

        return $this->json([
            'results' => $newsletterAudienceMapQuery->findMunicipalityChoicesWithinPolygon($polygonPoints),
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
        $campaignLocked = !$mailingCampaign->isEditable();
        $form = $this->createForm(EditMailingCampaignType::class, $formModel, [
            'locked' => $campaignLocked,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $campaignLocked) {
            $this->addFlash('error', 'mailing.flash.locked');

            return $this->redirectToRoute('mailing_content', [
                'uuid' => $mailingCampaign->getUuid(),
            ]);
        }

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
                removeBannerImage: $formModel->removeBannerImage,
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
                'campaignLocked' => $campaignLocked,
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
        ListMailingAudienceMasks $listMailingAudienceMasks,
    ): Response {
        $mailingCampaign = $getMailingCampaign($uuid);

        if (null === $mailingCampaign) {
            throw $this->createNotFoundException();
        }

        $audienceMaskForm = $this->createForm(
            MailingAudienceMaskType::class,
            new MailingAudienceMaskFormModel(),
            [
                'action' => $this->generateUrl('mailing_audience_mask_save_from_campaign', [
                    'campaignUuid' => $mailingCampaign->getUuid(),
                ]),
            ],
        );

        return $this->render('mailing/audience.html.twig', [
            'campaign' => $mailingCampaign,
            'campaignLocked' => !$mailingCampaign->isEditable(),
            'returnTo' => $request->query->getString('returnTo'),
            'audienceMasks' => $listMailingAudienceMasks(),
            'audienceMaskForm' => $audienceMaskForm->createView(),
        ]);
    }

    #[Route('/{uuid}/preview', name: 'preview', methods: ['GET'])]
    public function preview(
        Uuid $uuid,
        GetMailingCampaign $getMailingCampaign,
        NewsletterRendererInterface $newsletterRenderer,
        NewsletterAudienceResolverInterface $newsletterAudienceResolver,
    ): Response {
        $mailingCampaign = $getMailingCampaign($uuid);

        if (null === $mailingCampaign) {
            throw $this->createNotFoundException();
        }

        $activeRecommendationCount = count(array_filter(
            $mailingCampaign->getRecommendations(),
            static fn (MailingRecommendation $mailingRecommendation): bool => $mailingRecommendation->isActive(),
        ));

        $audienceRecipientCount = null;

        if ($mailingCampaign->getAudienceFilter()->hasActiveCriteria()) {
            try {
                $audienceRecipientCount = $newsletterAudienceResolver->resolve($mailingCampaign->getAudienceFilter(), 1)->getTotal();
            } catch (InvalidArgumentException) {
                $audienceRecipientCount = null;
            }
        }

        return $this->render('mailing/preview.html.twig', [
            'campaign' => $mailingCampaign,
            'renderedNewsletter' => $newsletterRenderer->render($mailingCampaign),
            'activeRecommendationCount' => $activeRecommendationCount,
            'hasAudienceCriteria' => $mailingCampaign->getAudienceFilter()->hasActiveCriteria(),
            'audienceRecipientCount' => $audienceRecipientCount,
        ]);
    }

    #[Route('/{uuid}/send', name: 'send', methods: ['GET', 'POST'])]
    public function send(
        Uuid $uuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        NewsletterAudienceResolverInterface $newsletterAudienceResolver,
        SendMailingCampaign $sendMailingCampaign,
    ): Response {
        $mailingCampaign = $getMailingCampaign($uuid);

        if (null === $mailingCampaign) {
            throw $this->createNotFoundException();
        }

        try {
            $audienceResolution = $newsletterAudienceResolver->resolve($mailingCampaign->getAudienceFilter(), 10);
            $audienceError = null;
        } catch (InvalidArgumentException) {
            $audienceResolution = null;
            $audienceError = 'mailing.send.invalid_audience';
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mailing_send_' . $mailingCampaign->getUuid()->toRfc4122(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            try {
                $sendMailingCampaign($mailingCampaign);
                $this->addFlash('success', 'mailing.flash.delivery_queued');

                return $this->redirectToRoute('mailing_index');
            } catch (InvalidArgumentException $invalidArgumentException) {
                $message = $invalidArgumentException->getMessage();
                $translationKey = match ($message) {
                    'Mailing campaign audience is empty.' => 'mailing.flash.delivery_empty',
                    'Mailing campaign delivery is already queued.' => 'mailing.flash.delivery_already_queued',
                    default => 'mailing.flash.delivery_failed',
                };
                $this->addFlash('error', $translationKey);

                return $this->redirectToRoute('mailing_send', [
                    'uuid' => $mailingCampaign->getUuid(),
                ]);
            }
        }

        return $this->render('mailing/send.html.twig', [
            'campaign' => $mailingCampaign,
            'audienceResolution' => $audienceResolution,
            'audienceError' => $audienceError,
            'queueWillUseCron' => true,
            'csrfToken' => $this->container->get('security.csrf.token_manager')->getToken('mailing_send_' . $mailingCampaign->getUuid()->toRfc4122())->getValue(),
        ]);
    }

    #[Route('/{uuid}/stop', name: 'stop', methods: ['POST'])]
    public function stop(
        Uuid $uuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        StopMailingCampaignDelivery $stopMailingCampaignDelivery,
    ): Response {
        $mailingCampaign = $getMailingCampaign($uuid);

        if (null === $mailingCampaign) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('mailing_stop_' . $mailingCampaign->getUuid()->toRfc4122(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $stopMailingCampaignDelivery($mailingCampaign);
            $this->addFlash('success', 'mailing.flash.delivery_stopped');
            $this->addFlash('info', 'mailing.flash.delivery_stopped_details');
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'mailing.flash.delivery_stop_failed');
        }

        return $this->redirectToRoute('mailing_index');
    }

    #[Route('/{uuid}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Uuid $uuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        DeleteMailingCampaign $deleteMailingCampaign,
    ): Response {
        $mailingCampaign = $getMailingCampaign($uuid);

        if (null === $mailingCampaign) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('mailing_delete_' . $mailingCampaign->getUuid()->toRfc4122(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $deleteMailingCampaign($mailingCampaign);
            $this->addFlash('success', 'mailing.flash.deleted');
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'mailing.flash.delete_failed');
        }

        return $this->redirectToRoute('mailing_index');
    }

    #[Route('/{uuid}/test', name: 'test', methods: ['GET', 'POST'])]
    public function test(
        Uuid $uuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        SendMailingCampaignTest $sendMailingCampaignTest,
    ): Response {
        $mailingCampaign = $getMailingCampaign($uuid);

        if (null === $mailingCampaign) {
            throw $this->createNotFoundException();
        }

        $formModel = new MailingTestFormModel();
        $user = $this->getUser();

        if ($user instanceof UserInterface) {
            $formModel->recipientEmail = $user->getUserIdentifier();
        }

        $campaignLocked = !$mailingCampaign->isEditable();
        $form = $this->createForm(MailingTestType::class, $formModel, [
            'locked' => $campaignLocked,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $campaignLocked) {
            $this->addFlash('error', 'mailing.flash.locked');

            return $this->redirectToRoute('mailing_test', [
                'uuid' => $mailingCampaign->getUuid(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $sendMailingCampaignTest($mailingCampaign, $formModel->recipientEmail);
                $this->addFlash('success', 'mailing.flash.test_queued');

                return $this->redirectToRoute('mailing_index');
            } catch (Throwable) {
                $this->addFlash('error', 'mailing.flash.test_failed');
            }
        }

        return $this->render(
            'mailing/test.html.twig',
            [
                'campaign' => $mailingCampaign,
                'campaignLocked' => $campaignLocked,
                'form' => $form->createView(),
            ],
            $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
    }
}
