<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\MailingAudienceMaskType;
use App\Application\Form\Model\MailingAudienceMaskFormModel;
use App\Application\Mailing\ApplyMailingAudienceMaskToCampaign;
use App\Application\Mailing\CreateMailingAudienceMask;
use App\Application\Mailing\CreateMailingAudienceMaskInput;
use App\Application\Mailing\GetMailingAudienceMask;
use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\ListMailingAudienceMasks;
use App\Application\Mailing\NewsletterAudienceMunicipalityMaterializerInterface;
use App\Domain\Model\Mailing\MailingCampaign;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/mailing/audience-masks', name: 'mailing_audience_mask_')]
final class MailingAudienceMaskController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ListMailingAudienceMasks $listMailingAudienceMasks,
        GetMailingCampaign $getMailingCampaign,
    ): Response {
        return $this->render('mailing/audience_mask/index.html.twig', [
            'campaign' => $this->resolveMailingCampaignFromQuery($request, $getMailingCampaign),
            'audienceMasks' => $listMailingAudienceMasks(),
        ]);
    }

    #[Route('/from-campaign/{campaignUuid}', name: 'save_from_campaign', methods: ['POST'])]
    public function saveFromCampaign(
        string $campaignUuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        CreateMailingAudienceMask $createMailingAudienceMask,
        NewsletterAudienceMunicipalityMaterializerInterface $newsletterAudienceMunicipalityMaterializer,
    ): Response {
        $mailingCampaign = $this->resolveMailingCampaign($campaignUuid, $getMailingCampaign);
        $form = $this->createForm(MailingAudienceMaskType::class, new MailingAudienceMaskFormModel());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formModel = $form->getData();

            if (!$formModel instanceof MailingAudienceMaskFormModel) {
                throw new InvalidArgumentException('Mailing audience mask form data is invalid.');
            }

            try {
                $createMailingAudienceMask(new CreateMailingAudienceMaskInput(
                    name: $formModel->name,
                    audienceFilter: $mailingCampaign->getAudienceFilter(),
                    materializedMunicipalityInseeCodes: $newsletterAudienceMunicipalityMaterializer->materialize($mailingCampaign->getAudienceFilter()),
                ));
                $this->addFlash('success', 'mailing.flash.audience_mask_created');
            } catch (InvalidArgumentException) {
                $this->addFlash('error', 'mailing.flash.audience_mask_create_failed');
            }
        } else {
            $this->addFlash('error', 'mailing.flash.audience_mask_create_failed');
        }

        return $this->redirectToRoute('mailing_audience', [
            'uuid' => $mailingCampaign->getUuid(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{maskUuid}/apply-to-campaign/{campaignUuid}', name: 'apply_to_campaign', methods: ['POST'])]
    public function applyToCampaign(
        string $maskUuid,
        string $campaignUuid,
        Request $request,
        GetMailingAudienceMask $getMailingAudienceMask,
        GetMailingCampaign $getMailingCampaign,
        ApplyMailingAudienceMaskToCampaign $applyMailingAudienceMaskToCampaign,
    ): Response {
        $mailingAudienceMask = Uuid::isValid($maskUuid) ? $getMailingAudienceMask(Uuid::fromString($maskUuid)) : null;
        $mailingCampaign = $this->resolveMailingCampaign($campaignUuid, $getMailingCampaign);

        if (null === $mailingAudienceMask) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid(
            'mailing_apply_audience_mask_' . $mailingAudienceMask->getUuid()->toRfc4122() . '_' . $mailingCampaign->getUuid()->toRfc4122(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException();
        }

        try {
            $applyMailingAudienceMaskToCampaign($mailingCampaign, $mailingAudienceMask);
            $this->addFlash('success', 'mailing.flash.audience_mask_applied');
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'mailing.flash.audience_mask_apply_failed');
        }

        return $this->redirectToRoute('mailing_audience', [
            'uuid' => $mailingCampaign->getUuid(),
        ], Response::HTTP_SEE_OTHER);
    }

    private function resolveMailingCampaign(string $campaignUuid, GetMailingCampaign $getMailingCampaign): MailingCampaign
    {
        if (!Uuid::isValid($campaignUuid)) {
            throw $this->createNotFoundException();
        }

        $mailingCampaign = $getMailingCampaign(Uuid::fromString($campaignUuid));

        if (!$mailingCampaign instanceof MailingCampaign) {
            throw $this->createNotFoundException();
        }

        return $mailingCampaign;
    }

    private function resolveMailingCampaignFromQuery(Request $request, GetMailingCampaign $getMailingCampaign): ?MailingCampaign
    {
        $campaignUuid = $request->query->getString('campaign');

        if ('' === $campaignUuid) {
            return null;
        }

        return $this->resolveMailingCampaign($campaignUuid, $getMailingCampaign);
    }
}
