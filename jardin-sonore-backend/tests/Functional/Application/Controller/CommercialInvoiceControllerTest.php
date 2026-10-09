<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialInvoiceControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testInvoiceEntryAndExplicitPayment(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("invoice-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $projectEntity = new CommercialProjectEntity('Ateliers 2027', $organizationEntity, null, new DateTimeImmutable());
        $projectEntity->setStatus(CommercialProjectEntity::STATUS_CONFIRMED);
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId() . '/invoices/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer la facture')->form();
        $form['commercial_invoice[reference]'] = "2026-09 - {$suffix}";
        $form['commercial_invoice[amount]'] = '310,00';
        $form['commercial_invoice[issuedOn]'] = '2026-09-01';
        $client->submit($form);
        self::assertResponseRedirects();

        $invoiceEntity = static::getContainer()->get(EntityManagerInterface::class)->getRepository(CommercialInvoiceEntity::class)->findOneBy(['reference' => "2026-09 - {$suffix}"]);
        self::assertInstanceOf(CommercialInvoiceEntity::class, $invoiceEntity);
        self::assertSame(31000, $invoiceEntity->getAmountCents());
        self::assertNotNull($invoiceEntity->getReminderAction());

        $client->request('POST', '/commercial/invoices/' . $invoiceEntity->getId() . '/pay', ['_token' => 'invalid', 'paidOn' => '2026-10-09']);
        self::assertResponseStatusCodeSame(403);
        $crawler = $client->request('GET', '/commercial/invoices');
        self::assertResponseIsSuccessful();
        $token = $crawler->filterXPath('//form[contains(@action, "/pay")]/input[@name="_token"]')->last()->attr('value');
        $client->request('POST', '/commercial/invoices/' . $invoiceEntity->getId() . '/pay', ['_token' => $token, 'paidOn' => '2026-10-09']);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedInvoiceEntity = $entityManager->find(CommercialInvoiceEntity::class, $invoiceEntity->getId());
        self::assertInstanceOf(CommercialInvoiceEntity::class, $savedInvoiceEntity);
        self::assertSame('2026-10-09', $savedInvoiceEntity->getPaidOn()?->format('Y-m-d'));
    }
}
