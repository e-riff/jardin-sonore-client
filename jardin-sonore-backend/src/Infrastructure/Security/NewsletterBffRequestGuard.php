<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class NewsletterBffRequestGuard
{
    public function __construct(
        #[Autowire('%env(PORTAL_BFF_SHARED_SECRET)%')] private string $bffSharedSecret,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onRequest(RequestEvent $requestEvent): void
    {
        $request = $requestEvent->getRequest();
        if (str_starts_with((string) $request->attributes->get('_route'), 'newsletter_api_')) {
            $this->assertAllowed($request);
        }
    }

    public function assertAllowed(Request $request): void
    {
        $providedSecret = $request->headers->get('X-Portal-Bff-Secret');
        if ('' === $this->bffSharedSecret || !is_string($providedSecret) || !hash_equals($this->bffSharedSecret, $providedSecret)) {
            throw new AccessDeniedHttpException();
        }
    }
}
