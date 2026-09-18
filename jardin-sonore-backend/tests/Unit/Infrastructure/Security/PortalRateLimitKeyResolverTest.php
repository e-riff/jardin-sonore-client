<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\PortalRateLimitKeyResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PortalRateLimitKeyResolverTest extends TestCase
{
    public function testItUsesTheForwardedVisitorIpOnlyWhenTheBffSecretMatches(): void
    {
        $portalRateLimitKeyResolver = new PortalRateLimitKeyResolver('shared-secret');
        $request = Request::create('/', server: [
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_PORTAL_BFF_SECRET' => 'shared-secret',
            'HTTP_X_PORTAL_CLIENT_IP' => '198.51.100.12',
        ]);

        self::assertSame('198.51.100.12', $portalRateLimitKeyResolver->resolve($request));
    }

    public function testItRejectsAnUntrustedForwardedVisitorIp(): void
    {
        $portalRateLimitKeyResolver = new PortalRateLimitKeyResolver('shared-secret');
        $request = Request::create('/', server: [
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_PORTAL_BFF_SECRET' => 'untrusted',
            'HTTP_X_PORTAL_CLIENT_IP' => '198.51.100.12',
        ]);

        self::assertSame('10.0.0.10', $portalRateLimitKeyResolver->resolve($request));
    }
}
