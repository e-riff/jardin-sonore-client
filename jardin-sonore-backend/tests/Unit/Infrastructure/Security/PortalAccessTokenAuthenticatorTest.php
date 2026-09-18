<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Application\Portal\PortalSessionManager;
use App\Infrastructure\Security\PortalAccessTokenAuthenticator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;

final class PortalAccessTokenAuthenticatorTest extends TestCase
{
    public function testItSupportsOnlyBearerAuthenticatedRequests(): void
    {
        $portalAccessTokenAuthenticator = new PortalAccessTokenAuthenticator(
            new PortalSessionManager($this->createStub(EntityManagerInterface::class), new MockClock(new DateTimeImmutable()), 604800),
        );

        self::assertTrue($portalAccessTokenAuthenticator->supports(Request::create('/api/portal/sessions', server: ['HTTP_AUTHORIZATION' => 'Bearer opaque-token'])));
        self::assertFalse($portalAccessTokenAuthenticator->supports(Request::create('/api/portal/sessions')));
    }
}
