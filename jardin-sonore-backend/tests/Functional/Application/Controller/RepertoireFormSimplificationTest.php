<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class RepertoireFormSimplificationTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testRepertoireFormHasTwoSaveActionsAndCatalogMediaDialog(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())
            ->setEmail('repertoire-form-' . bin2hex(random_bytes(5)) . '@example.test')
            ->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/sessions/repertoire/new');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filterXPath('//form[contains(@class, "internal-form")][1]//button[@type="submit"]'));
        self::assertCount(1, $crawler->filterXPath('//dialog[contains(@class, "media-association-picker__dialog")]'));
        self::assertCount(1, $crawler->filterXPath('//input[@name="repertoire_item[active]"][@form="repertoire_item"]'));
        self::assertCount(0, $crawler->filterXPath('//*[contains(@name, "youtubeVideoUrl") or contains(@name, "[notes]")]'));
    }
}
