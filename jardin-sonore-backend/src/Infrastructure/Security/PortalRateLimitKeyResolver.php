<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

final readonly class PortalRateLimitKeyResolver
{
    public function __construct(
        #[Autowire('%env(PORTAL_BFF_SHARED_SECRET)%')]
        private string $portalBffSharedSecret,
    ) {
    }

    public function resolve(Request $request): string
    {
        $forwardedClientIp = $request->headers->get('X-Portal-Client-IP');
        $bffSecret = $request->headers->get('X-Portal-Bff-Secret');

        if ('' !== $this->portalBffSharedSecret
            && is_string($forwardedClientIp)
            && filter_var($forwardedClientIp, FILTER_VALIDATE_IP)
            && is_string($bffSecret)
            && hash_equals($this->portalBffSharedSecret, $bffSecret)) {
            return $forwardedClientIp;
        }

        return $request->getClientIp() ?? 'unknown';
    }
}
