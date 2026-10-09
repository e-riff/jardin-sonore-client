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
}
