<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;
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
        self::assertCount(1, $crawler->filterXPath('//form[@id="repertoire_item"]'));
        self::assertCount(0, $crawler->filterXPath('//*[contains(@name, "youtubeVideoUrl") or contains(@name, "[notes]")]'));
        self::assertCount(1, $crawler->filterXPath('//input[@name="repertoire_item[active]"][@checked]'));

        $title = 'Comptine active par défaut ' . bin2hex(random_bytes(5));
        $form = $crawler->filterXPath('//form[@id="repertoire_item"]')->form();
        $form['repertoire_item[title]'] = $title;
        $form['repertoire_item[contentBlocks][0][text]'] = 'Une ligne de paroles';
        $client->submit($form);

        self::assertResponseRedirects();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $createdRepertoireItemEntity = $entityManager->getRepository(RepertoireItemEntity::class)->findOneBy(['title' => $title]);
        self::assertInstanceOf(RepertoireItemEntity::class, $createdRepertoireItemEntity);
        self::assertTrue($createdRepertoireItemEntity->isActive());
    }

    public function testRepertoireStatusIsSavedInBothDirections(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())
            ->setEmail('repertoire-status-' . bin2hex(random_bytes(5)) . '@example.test')
            ->setPassword('unused');
        $repertoireItemEntity = (new RepertoireItemEntity())
            ->setTitle('Comptine de test')
            ->setSlug('repertoire-status-' . bin2hex(random_bytes(5)))
            ->setContentBlocks([['kind' => 'line', 'text' => 'Une ligne de paroles', 'gesture' => null]])
            ->setActive(false);
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($repertoireItemEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);
        $repertoireItemUuid = $repertoireItemEntity->getUuid();
        $editUrl = '/sessions/repertoire/' . $repertoireItemUuid->toRfc4122() . '/edit';

        foreach ([true, false] as $active) {
            $crawler = $client->request('GET', $editUrl);
            self::assertResponseIsSuccessful();
            self::assertCount(1, $crawler->filterXPath('//form[@id="repertoire_item"]'));
            $form = $crawler->filterXPath('//form[@id="repertoire_item"]')->form();
            $form['repertoire_item[active]']->setValue($active);
            $client->submit($form);

            self::assertResponseRedirects();
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();
            $savedRepertoireItemEntity = $entityManager->getRepository(RepertoireItemEntity::class)->findOneBy(['uuid' => $repertoireItemUuid]);
            self::assertInstanceOf(RepertoireItemEntity::class, $savedRepertoireItemEntity);
            self::assertSame($active, $savedRepertoireItemEntity->isActive());

            $crawler = $client->request('GET', $editUrl);
            self::assertResponseIsSuccessful();
            self::assertCount(1, $crawler->filterXPath('//input[@name="repertoire_item[active]"][' . ($active ? '@checked' : 'not(@checked)') . ']'));
        }
    }
}
