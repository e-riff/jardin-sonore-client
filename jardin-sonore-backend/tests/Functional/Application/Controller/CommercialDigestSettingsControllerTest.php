<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialDigestSettingsEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialDigestSettingsControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testAdminCanPauseDigestAndChangeTime(): void
    {
        $client = static::createClient();
        $client->request('GET', '/commercial/digest/settings');
        self::assertResponseRedirects('/login');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('digest-' . bin2hex(random_bytes(5)) . '@example.test')->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/digest/settings');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer les réglages')->form();
        $form['commercial_digest_settings[enabled]'] = false;
        $form['commercial_digest_settings[sendTime]'] = '09:15';
        $client->submit($form);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $settingsEntity = $entityManager->find(CommercialDigestSettingsEntity::class, CommercialDigestSettingsEntity::SINGLETON_ID);
        self::assertInstanceOf(CommercialDigestSettingsEntity::class, $settingsEntity);
        self::assertFalse($settingsEntity->isEnabled());
        self::assertSame('09:15', $settingsEntity->getSendTime());

        $settingsEntity->setEnabled(true);
        $settingsEntity->setSendTime('08:00');
        $entityManager->flush();
    }
}
