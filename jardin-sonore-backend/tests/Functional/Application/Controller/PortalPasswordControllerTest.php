<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class PortalPasswordControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-sonore-portal-password-controller-cache';
            }
        };
    }

    public function testTheLegacyBackendPasswordPageIsUnavailable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/portail/definir-mot-de-passe/unknown-token');

        self::assertResponseStatusCodeSame(404);
    }
}
