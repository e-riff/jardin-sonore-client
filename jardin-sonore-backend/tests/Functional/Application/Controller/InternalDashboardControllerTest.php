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

    public function testBrandSeparatesInternalDashboardAndPublicSiteLinks(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())
            ->setEmail('admin-' . bin2hex(random_bytes(8)) . '@header.test')
            ->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $publicSiteUrl = static::getContainer()->getParameter('app.portal.public_base_url');
        $brandBlock = $crawler->filterXPath('//*[contains(concat(" ", normalize-space(@class), " "), " internal-header__brand-block ")]');
        self::assertSame('/', $brandBlock->filterXPath('descendant::a[contains(concat(" ", normalize-space(@class), " "), " internal-brand ")]')->attr('href'));
        $publicSiteLink = $brandBlock->filterXPath('descendant::a[contains(concat(" ", normalize-space(@class), " "), " internal-public-site-link ")]');
        self::assertCount(1, $publicSiteLink);
        self::assertSame($publicSiteUrl, $publicSiteLink->attr('href'));
        self::assertSame('_blank', $publicSiteLink->attr('target'));
        self::assertSame('noopener noreferrer', $publicSiteLink->attr('rel'));
        self::assertSame('Retour au site public', trim($publicSiteLink->text()));
        self::assertCount(0, $crawler->filterXPath('//*[contains(concat(" ", normalize-space(@class), " "), " internal-header__meta ") or contains(concat(" ", normalize-space(@class), " "), " internal-mobile-nav-panel__footer ")]/a[contains(concat(" ", normalize-space(@class), " "), " internal-public-site-link ")]'));
        self::assertCount(2, $crawler->filterXPath('//*[contains(concat(" ", normalize-space(@class), " "), " internal-header__meta ") or contains(concat(" ", normalize-space(@class), " "), " internal-mobile-nav-panel__footer ")]/span[normalize-space(text())="Interface interne"]'));
    }
}
