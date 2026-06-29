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
use App\Application\Mailing\UpdateMailingCampaign;
use App\Application\Mailing\UpdateMailingCampaignInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        string $uuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        UpdateMailingCampaign $updateMailingCampaign,
    ): Response {
        if (!Uuid::isValid($uuid)) {
            throw $this->createNotFoundException();
        }

        $mailingCampaign = $getMailingCampaign(Uuid::fromString($uuid));

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
                templateKey: $formModel->templateKey,
            ));

            $this->addFlash('success', 'mailing.flash.updated');

            return $this->redirectToRoute('mailing_content', [
                'uuid' => $mailingCampaign->getUuid(),
            ]);
        }

        return $this->render(
            'mailing/content.html.twig',
            [
                'campaign' => $mailingCampaign,
                'form' => $form->createView(),
                'formSubmitted' => $form->isSubmitted(),
            ],
            $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
    }
}
