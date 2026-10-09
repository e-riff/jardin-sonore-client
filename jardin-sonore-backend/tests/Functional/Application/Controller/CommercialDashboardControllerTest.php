<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialDashboardControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testDashboardNeedsLoginAndDisplaysRequestsActionsAndProjectsWithoutAction(): void
    {
        $client = static::createClient();
        $client->request('GET', '/commercial');
        self::assertResponseRedirects('/login');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("dashboard-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $requestEntity = new CommercialRequestEntity('manual', "Demande {$suffix}", '', 'Premier contact', new DateTimeImmutable());
        $projectEntity = new CommercialProjectEntity("Dossier sans action {$suffix}", $organizationEntity, null, new DateTimeImmutable());
        $projectWithActionEntity = new CommercialProjectEntity("Dossier à rappeler {$suffix}", $organizationEntity, null, new DateTimeImmutable());
        $projectWithActionEntity->addAction(new CommercialActionEntity($projectWithActionEntity, "Relancer {$suffix}", new DateTimeImmutable('yesterday')));
        foreach ([$adminUserEntity, $organizationEntity, $requestEntity, $projectEntity, $projectWithActionEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('GET', '/commercial');
        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString("Demande {$suffix}", $content);
        self::assertStringContainsString("Relancer {$suffix}", $content);
        self::assertStringContainsString("Dossier sans action {$suffix}", $content);
        self::assertSame(CommercialRequestEntity::STATUS_TO_QUALIFY, $requestEntity->getStatus());
        self::assertSame(CommercialActionEntity::STATUS_OPEN, $projectWithActionEntity->getActions()->first()->getStatus());
    }
}
