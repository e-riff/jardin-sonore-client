<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Infrastructure\Google\GoogleCalendarOAuthClient;
use App\Infrastructure\Google\GoogleCalendarOAuthException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commercial/calendar', name: 'commercial_calendar_')]
final class GoogleCalendarConnectionController extends AbstractController
{
    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function settings(GoogleCalendarOAuthClient $oauthClient): Response
    {
        return $this->render('commercial/calendar/settings.html.twig', [
            'configured' => $oauthClient->isConfigured(),
            'connection' => $oauthClient->connection(),
        ]);
    }

    #[Route('/connect', name: 'connect', methods: ['POST'])]
    public function connect(Request $request, GoogleCalendarOAuthClient $oauthClient): RedirectResponse
    {
        $this->validateToken('commercial_calendar_connect', $request);

        try {
            $state = bin2hex(random_bytes(32));
            $request->getSession()->set('google_calendar_oauth_state', $state);

            return $this->redirect($oauthClient->authorizationUrl($state));
        } catch (GoogleCalendarOAuthException) {
            $this->addFlash('error', ['message' => 'calendar.connection.configuration_missing', 'domain' => 'commercial']);

            return $this->redirectToRoute('commercial_calendar_settings');
        }
    }

    #[Route('/callback', name: 'callback', methods: ['GET'])]
    public function callback(Request $request, GoogleCalendarOAuthClient $oauthClient): RedirectResponse
    {
        $session = $request->getSession();
        $expectedState = $session->get('google_calendar_oauth_state');
        $session->remove('google_calendar_oauth_state');
        $returnedState = $request->query->getString('state');
        if (!is_string($expectedState) || '' === $expectedState || '' === $returnedState || !hash_equals($expectedState, $returnedState)) {
            throw $this->createAccessDeniedException();
        }

        if ($request->query->has('error')) {
            $this->addFlash('error', ['message' => 'calendar.connection.denied', 'domain' => 'commercial']);

            return $this->redirectToRoute('commercial_calendar_settings');
        }

        try {
            $oauthClient->connectWithCode($request->query->getString('code'));
            $this->addFlash('success', ['message' => 'calendar.connection.connected', 'domain' => 'commercial']);
        } catch (GoogleCalendarOAuthException) {
            $this->addFlash('error', ['message' => 'calendar.connection.failed', 'domain' => 'commercial']);
        }

        return $this->redirectToRoute('commercial_calendar_settings');
    }

    #[Route('/disconnect', name: 'disconnect', methods: ['POST'])]
    public function disconnect(Request $request, GoogleCalendarOAuthClient $oauthClient): RedirectResponse
    {
        $this->validateToken('commercial_calendar_disconnect', $request);
        $revoked = $oauthClient->disconnect();
        $this->addFlash($revoked ? 'success' : 'warning', [
            'message' => $revoked ? 'calendar.connection.disconnected' : 'calendar.connection.local_disconnected',
            'domain' => 'commercial',
        ]);

        return $this->redirectToRoute('commercial_calendar_settings');
    }

    private function validateToken(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
