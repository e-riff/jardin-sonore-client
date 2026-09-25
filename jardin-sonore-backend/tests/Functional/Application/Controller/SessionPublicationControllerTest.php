<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class SessionPublicationControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-sonore-session-publication-test-cache';
            }
        };
    }

    public function testAdminCanPublishFromSessionListWithCsrf(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('admin-' . bin2hex(random_bytes(8)) . '@portal.test')->setPassword('unused');
        $sessionTitle = 'Séance brouillon ' . bin2hex(random_bytes(8));
        $sessionSummaryEntity = (new SessionSummaryEntity())->setTitle($sessionTitle);
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($sessionSummaryEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('GET', '/sessions/new');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('name="session_summary[published]"', (string) $client->getResponse()->getContent());

        $client->request('GET', '/sessions/' . $sessionSummaryEntity->getUuid()->toRfc4122() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('name="session_summary[published]"', (string) $client->getResponse()->getContent());

        $crawler = $client->request('GET', '/sessions?query=' . urlencode($sessionTitle));
        self::assertResponseIsSuccessful();
        $publicationButton = $crawler->filterXPath('//button[@data-controller="session-publication"]')->first();
        self::assertCount(1, $publicationButton);
        self::assertSame('switch', $publicationButton->attr('role'));
        self::assertSame('false', $publicationButton->attr('aria-checked'));
        self::assertCount(1, $crawler->filterXPath('//tr[@data-controller="catalog-row" and @data-catalog-row-url-value="/sessions/' . $sessionSummaryEntity->getUuid()->toRfc4122() . '/edit"]'));
        self::assertCount(0, $crawler->filterXPath('//tr[@data-controller="catalog-row"]//a[@title="Modifier"]'));
        self::assertCount(1, $crawler->filterXPath('//td[@data-table-mobile="publication"]//button[@data-controller="session-publication"]'));
        self::assertCount(1, $publicationButton->filterXPath('.//span[contains(@class, "internal-visually-hidden")]'));
        $csrfToken = $publicationButton->attr('data-session-publication-token-value');
        self::assertNotNull($csrfToken);

        $publicationPath = '/sessions/' . $sessionSummaryEntity->getUuid()->toRfc4122() . '/publication';
        $client->request('POST', $publicationPath, ['_token' => 'invalid', 'published' => '1']);
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', $publicationPath, ['_token' => $csrfToken, 'published' => '1']);
        self::assertResponseIsSuccessful();
        self::assertSame(['published' => true], json_decode((string) $client->getResponse()->getContent(), true));
        $reloadedEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $reloadedSessionSummaryEntity = $reloadedEntityManager->getRepository(SessionSummaryEntity::class)->findOneBy(['uuid' => $sessionSummaryEntity->getUuid()]);
        self::assertInstanceOf(SessionSummaryEntity::class, $reloadedSessionSummaryEntity);
        self::assertTrue($reloadedSessionSummaryEntity->isPublished());

        $editCrawler = $client->request('GET', '/sessions/' . $sessionSummaryEntity->getUuid()->toRfc4122() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $editCrawler->filterXPath('//input[@name="session_summary[published]" and @checked]'));
    }

    public function testSessionListCanFilterByPublicationAndCategory(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('admin-' . bin2hex(random_bytes(8)) . '@portal.test')->setPassword('unused');
        $matchingThemeEntity = (new ThemeEntity())->setLabel('Pluie ' . bin2hex(random_bytes(4)))->setColor('#2563eb');
        $otherThemeEntity = (new ThemeEntity())->setLabel('Nuit ' . bin2hex(random_bytes(4)))->setColor('#312e81');
        $sessionPrefix = 'Séances filtrées ' . bin2hex(random_bytes(8));
        $publishedSessionEntity = (new SessionSummaryEntity())->setTitle($sessionPrefix . ' publiée')->setPublished(true)->addTheme($matchingThemeEntity);
        $unpublishedSessionEntity = (new SessionSummaryEntity())->setTitle($sessionPrefix . ' brouillon')->addTheme($otherThemeEntity);
        foreach ([$adminUserEntity, $matchingThemeEntity, $otherThemeEntity, $publishedSessionEntity, $unpublishedSessionEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/sessions?query=' . urlencode($sessionPrefix) . '&publication=published&theme=' . $matchingThemeEntity->getUuid()->toRfc4122());
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filterXPath('//tbody/tr'));
        self::assertStringContainsString($publishedSessionEntity->getTitle(), $crawler->filterXPath('//tbody/tr')->text());
        self::assertStringContainsString($matchingThemeEntity->getLabel(), $crawler->filterXPath('//tbody/tr')->text());
        self::assertCount(1, $crawler->filterXPath('//select[@name="publication"]/option[@value="published" and @selected]'));
        self::assertCount(1, $crawler->filterXPath('//select[@name="theme"]/option[@value="' . $matchingThemeEntity->getUuid()->toRfc4122() . '" and @selected]'));

        $crawler = $client->request('GET', '/sessions?query=' . urlencode($sessionPrefix) . '&publication=unpublished');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filterXPath('//tbody/tr'));
        self::assertStringContainsString($unpublishedSessionEntity->getTitle(), $crawler->filterXPath('//tbody/tr')->text());
    }
}
