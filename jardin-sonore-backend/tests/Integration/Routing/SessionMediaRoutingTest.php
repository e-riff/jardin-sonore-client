<?php

declare(strict_types=1);

namespace App\Tests\Integration\Routing;

use App\Kernel;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

final class SessionMediaRoutingTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-sonore-session-media-routing-cache';
            }
        };
    }

    #[Test]
    public function selectingCatalogMediaUsesTheDedicatedAddRoute(): void
    {
        self::bootKernel();

        $router = self::getContainer()->get('router.default');

        self::assertInstanceOf(RequestMatcherInterface::class, $router);

        $routeParameters = $router->matchRequest(Request::create(
            '/sessions/01890f44-7c1f-7ee7-b90f-2ce81c76a3de/sequences/01890f44-7c20-7ee7-b90f-2ce81c76a3de/media',
            Request::METHOD_POST,
        ));

        self::assertSame('session_sequence_media_add', $routeParameters['_route']);
    }
}
