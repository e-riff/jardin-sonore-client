<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Domain\Model\Mailing\MailingCampaignType;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Mailing\MailingPreferenceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MailingPreferencesController extends AbstractController
{
    #[Route('/mailing/preferences/{token}', name: 'mailing_preferences', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, string $token, EmailContactDoctrineRepository $repository, MailingPreferenceManager $manager): Response
    {
        $contact = $repository->findOneBy(['unsubscribeToken' => $token]);
        if (null === $contact || '' === $token) {
            throw $this->createNotFoundException();
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mailing_preferences_' . $token, $request->request->getString('_token'))) {
                return $this->render('newsletter/preferences.html.twig', ['contact' => $contact, 'token' => $token, 'saved' => false, 'requestError' => true], new Response(status: Response::HTTP_FORBIDDEN));
            }
            $action = $request->request->getString('action');
            if (!in_array($action, ['newsletter', 'outreach', 'all'], true)) {
                throw $this->createNotFoundException();
            }
            $manager->refuse($contact->getUuid()->toRfc4122(), 'all' === $action ? null : MailingCampaignType::from($action));

            return $this->redirectToRoute('mailing_preferences', ['token' => $token, 'saved' => 1]);
        }
        $response = $this->render('newsletter/preferences.html.twig', ['contact' => $contact, 'token' => $token, 'saved' => $request->query->getBoolean('saved')]);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
