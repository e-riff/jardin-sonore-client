<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\CreateMailingCampaignType;
use App\Application\Form\Model\CreateMailingCampaignFormModel;
use App\Application\Mailing\CreateMailingCampaign;
use App\Application\Mailing\CreateMailingCampaignInput;
use App\Application\Mailing\ListMailingCampaigns;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
            $createMailingCampaign(new CreateMailingCampaignInput(
                internalTitle: $formModel->internalTitle,
                emailSubject: $formModel->emailSubject,
                publicTitle: $formModel->publicTitle,
                mainText: $formModel->mainText,
            ));

            $this->addFlash('success', 'mailing.flash.created');

            return $this->redirectToRoute('mailing_index');
        }

        return $this->render('mailing/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
