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

    public function testDashboardNeedsLoginAndGroupsProjectsByTheirNextTask(): void
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
        $provisionalProjectEntity = new CommercialProjectEntity("Dossier provisoire {$suffix}", null, $requestEntity, new DateTimeImmutable());
        $projectWithActionEntity = new CommercialProjectEntity("Dossier à rappeler {$suffix}", $organizationEntity, null, new DateTimeImmutable());
        $projectWithActionEntity->addAction(new CommercialActionEntity($projectWithActionEntity, "Relancer {$suffix}", new DateTimeImmutable('yesterday')));
        foreach ([$adminUserEntity, $organizationEntity, $requestEntity, $projectEntity, $provisionalProjectEntity, $projectWithActionEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('GET', '/commercial');
        self::assertResponseRedirects('/commercial/projects');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString("Demande {$suffix}", $content);
        self::assertStringContainsString("Relancer {$suffix}", $content);
        self::assertStringContainsString("Dossier sans action {$suffix}", $content);
        self::assertStringContainsString("Dossier provisoire {$suffix}", $content);
        self::assertStringContainsString('Dossiers urgents', $content);
        self::assertStringContainsString('/commercial/projects/' . $projectWithActionEntity->getId(), $content);
        self::assertStringNotContainsString('/commercial/requests/' . $requestEntity->getId() . '"', $content);
        self::assertSame(1, substr_count($content, 'data-project-id="' . $projectWithActionEntity->getId() . '"'));
        self::assertCount(0, $crawler->filterXPath('//nav[contains(@class, "internal-subnav")]//a[@href="/commercial"]'));
        self::assertCount(1, $crawler->filterXPath('//nav[contains(@class, "internal-subnav")]//a[@href="/commercial/projects"]'));
        self::assertStringContainsString('<option value="completed"', $content);
        self::assertSame(CommercialRequestEntity::STATUS_TO_QUALIFY, $requestEntity->getStatus());
        self::assertSame(CommercialActionEntity::STATUS_OPEN, $projectWithActionEntity->getActions()->first()->getStatus());
    }

    public function testProjectIndexRequiresLoginAndRendersInstantFilters(): void
    {
        $client = static::createClient();
        $client->request('GET', '/commercial/projects');
        self::assertResponseRedirects('/login');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-index-{$suffix}@example.test")->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('GET', '/commercial/projects');
        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('data-model="debounce(300)|query"', $content);
        self::assertStringContainsString('data-model="on(change)|status"', $content);
        self::assertStringContainsString('data-model="on(change)|sort"', $content);
    }
}
