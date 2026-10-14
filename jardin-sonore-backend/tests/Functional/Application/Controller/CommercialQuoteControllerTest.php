<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialQuoteEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialQuoteControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testSentQuoteCanBeRecordedAndThenSigned(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("quote-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Mairie {$suffix}");
        $projectEntity = new CommercialProjectEntity('Ateliers 2027', $organizationEntity, null, new DateTimeImmutable());
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId() . '/quotes/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer le devis envoyé')->form();
        $form['commercial_quote[reference]'] = "2026-10 - d{$suffix}";
        $form['commercial_quote[filename]'] = "2026-10 - d{$suffix} - Mairie";
        $form['commercial_quote[amount]'] = '310,50';
        $form['commercial_quote[sentOn]'] = '2026-10-09';
        $client->submit($form);
        self::assertResponseRedirects();

        $quoteEntity = static::getContainer()->get(EntityManagerInterface::class)->getRepository(CommercialQuoteEntity::class)->findOneBy(['reference' => "2026-10 - d{$suffix}"]);
        self::assertInstanceOf(CommercialQuoteEntity::class, $quoteEntity);
        self::assertSame(31050, $quoteEntity->getAmountCents());

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $token = $crawler->filterXPath('//form[contains(@action, "/quotes/")]/input[@name="_token"]')->attr('value');
        $client->request('POST', '/commercial/quotes/' . $quoteEntity->getId() . '/sign', ['_token' => $token, 'signedOn' => '2026-10-10']);
        self::assertResponseRedirects();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedQuoteEntity = $entityManager->find(CommercialQuoteEntity::class, $quoteEntity->getId());
        self::assertInstanceOf(CommercialQuoteEntity::class, $savedQuoteEntity);
        self::assertSame('2026-10-10', $savedQuoteEntity->getSignedOn()?->format('Y-m-d'));
        self::assertSame(CommercialProjectEntity::STATUS_CONFIRMED, $savedQuoteEntity->getProject()->getStatus());
    }

    public function testSentQuoteCanBeMarkedAsRefused(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("refusal-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Mairie {$suffix}");
        $projectEntity = new CommercialProjectEntity('Projet partenariat', $organizationEntity, null, new DateTimeImmutable());
        $quoteEntity = new CommercialQuoteEntity($projectEntity, "D5-{$suffix}", 'devis-d5.pdf', 25000, new DateTimeImmutable('2026-10-10'));
        $replacementQuoteEntity = new CommercialQuoteEntity($projectEntity, "D6-{$suffix}", 'devis-d6.pdf', 26000, new DateTimeImmutable('2026-10-14'), $quoteEntity);
        $projectEntity->addQuote($quoteEntity);
        $projectEntity->addQuote($replacementQuoteEntity);
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity, $quoteEntity, $replacementQuoteEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Envoyé', $crawler->text());
        self::assertStringContainsString('Marquer comme refusé', $crawler->text());
        $token = $crawler->filterXPath(sprintf('//form[@action="/commercial/quotes/%d/refuse"]/input[@name="_token"]', $quoteEntity->getId()))->attr('value');

        $client->request('POST', '/commercial/quotes/' . $quoteEntity->getId() . '/refuse', ['_token' => $token, 'refusedOn' => '2026-10-14']);

        self::assertResponseRedirects('/commercial/projects/' . $projectEntity->getId());
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedQuoteEntity = $entityManager->find(CommercialQuoteEntity::class, $quoteEntity->getId());
        self::assertInstanceOf(CommercialQuoteEntity::class, $savedQuoteEntity);
        self::assertSame('2026-10-14', $savedQuoteEntity->getRefusedOn()?->format('Y-m-d'));
    }
}
