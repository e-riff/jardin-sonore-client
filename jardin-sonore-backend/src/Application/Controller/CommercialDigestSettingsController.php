<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\CommercialDigestSettingsType;
use App\Infrastructure\Doctrine\Entity\CommercialDigestSettingsEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommercialDigestSettingsController extends AbstractController
{
    #[Route('/commercial/digest/settings', name: 'commercial_digest_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, EntityManagerInterface $entityManager): Response
    {
        $settingsEntity = $entityManager->find(CommercialDigestSettingsEntity::class, CommercialDigestSettingsEntity::SINGLETON_ID);
        if (!$settingsEntity instanceof CommercialDigestSettingsEntity) {
            throw $this->createNotFoundException();
        }
        $form = $this->createForm(CommercialDigestSettingsType::class, [
            'enabled' => $settingsEntity->isEnabled(),
            'sendTime' => $settingsEntity->getSendTime(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{enabled: bool, sendTime: string} $data */
            $data = $form->getData();
            $settingsEntity->setEnabled($data['enabled']);
            $settingsEntity->setSendTime($data['sendTime']);
            $entityManager->flush();

            return $this->redirectToRoute('commercial_digest_settings');
        }

        return $this->render('commercial/digest/settings.html.twig', ['form' => $form]);
    }
}
