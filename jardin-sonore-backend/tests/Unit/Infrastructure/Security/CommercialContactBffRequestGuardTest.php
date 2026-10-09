<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\CommercialContactBffRequestGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class CommercialContactBffRequestGuardTest extends TestCase
{
    public function testCorrectServerSecretIsAccepted(): void
    {
        $this->expectNotToPerformAssertions();
        $request = Request::create('/api/commercial/contact-requests', 'POST');
        $request->headers->set('X-Portal-Bff-Secret', 'server-secret');

        (new CommercialContactBffRequestGuard('server-secret'))->assertAllowed($request);
    }

    public function testMissingSecretIsRejected(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        (new CommercialContactBffRequestGuard('server-secret'))->assertAllowed(Request::create('/api/commercial/contact-requests', 'POST'));
    }

    public function testWrongSecretIsRejected(): void
    {
        $request = Request::create('/api/commercial/contact-requests', 'POST');
        $request->headers->set('X-Portal-Bff-Secret', 'wrong-secret');

        $this->expectException(AccessDeniedHttpException::class);
        (new CommercialContactBffRequestGuard('server-secret'))->assertAllowed($request);
    }
}
