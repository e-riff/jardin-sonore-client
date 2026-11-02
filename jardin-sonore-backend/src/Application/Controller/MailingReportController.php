<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\MailingDeliveryQueueInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class MailingReportController extends AbstractController
{
    #[Route('/mailing/{uuid}/bilan', name: 'mailing_report', methods: ['GET'])]
    public function __invoke(string $uuid, GetMailingCampaign $getMailingCampaign, MailingDeliveryQueueInterface $deliveryQueue): Response
    {
        if (!Uuid::isValid($uuid) || null === $campaign = $getMailingCampaign(Uuid::fromString($uuid))) {
            throw $this->createNotFoundException();
        }

        return $this->render('mailing/report.html.twig', [
            'campaign' => $campaign,
            'counts' => $deliveryQueue->getCampaignDeliveryCounts($uuid),
        ]);
    }
}
