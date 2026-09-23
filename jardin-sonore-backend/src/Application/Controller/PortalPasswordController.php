<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\PortalSetPasswordType;
use App\Application\Portal\PortalPasswordTokenManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class PortalPasswordController extends AbstractController
{
    public function __construct(private PortalPasswordTokenManager $portalPasswordTokenManager, private Environment $twig)
    {
    }

    public function set(Request $request, string $token): Response
    {
        $userPasswordTokenEntity = $this->portalPasswordTokenManager->findUsable($token);

        if (null === $userPasswordTokenEntity) {
            return new Response($this->twig->render('portal_password/unavailable.html.twig'), Response::HTTP_NOT_FOUND);
        }

        $form = $this->createForm(PortalSetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->portalPasswordTokenManager->consumeWithPassword($userPasswordTokenEntity, (string) $form->get('password')->getData());

            return new Response($this->twig->render('portal_password/success.html.twig'));
        }

        return new Response($this->twig->render('portal_password/set.html.twig', ['form' => $form->createView()]));
    }
}
