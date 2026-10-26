<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class InternalDashboardControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testInternalDashboardRedirectsToClientFollowUp(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())
            ->setEmail('admin-' . bin2hex(random_bytes(8)) . '@header.test')
            ->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('GET', '/');

        self::assertResponseRedirects('/commercial/projects');
    }
}
