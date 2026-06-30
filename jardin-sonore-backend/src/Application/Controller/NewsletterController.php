<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Mailing\UnsubscribeNewsletterRecipient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/newsletter', name: 'newsletter_')]
final class NewsletterController extends AbstractController
{
    #[Route('/unsubscribe/{token}', name: 'unsubscribe', methods: ['GET'])]
    public function unsubscribe(
        string $token,
        UnsubscribeNewsletterRecipient $unsubscribeNewsletterRecipient,
    ): Response {
        return $this->render('newsletter/unsubscribe.html.twig', [
            'success' => $unsubscribeNewsletterRecipient($token),
        ]);
    }
}
