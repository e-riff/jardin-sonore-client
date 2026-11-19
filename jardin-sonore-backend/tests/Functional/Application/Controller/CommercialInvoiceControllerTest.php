<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoicePreparationEntity;
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

    public function testSentInvoiceClosesPreparationWithoutTrackingPayment(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("invoice-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $projectEntity = new CommercialProjectEntity('Ateliers 2027', $organizationEntity, null, new DateTimeImmutable());
        $projectEntity->setStatus(CommercialProjectEntity::STATUS_CONFIRMED);
        $actionEntity = new CommercialActionEntity($projectEntity, 'Préparer la facture', new DateTimeImmutable('2026-09-15'));
        $projectEntity->addAction($actionEntity);
        $preparationEntity = new CommercialInvoicePreparationEntity($projectEntity, new DateTimeImmutable('2026-09-01'), $actionEntity);
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity, $preparationEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $actionId = $actionEntity->getId();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId() . '/invoices/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer la facture')->form();
        $form['commercial_invoice[reference]'] = "2026-09 - {$suffix}";
        $form['commercial_invoice[amount]'] = '310,00';
        $form['commercial_invoice[description]'] = 'Trois séances de septembre';
        $form['commercial_invoice[serviceMonth]'] = '2026-09-01';
        $invalidValues = $form->getPhpValues();
        $invalidValues['commercial_invoice']['reference'] = str_repeat('X', 65);
        $client->request('POST', '/commercial/projects/' . $projectEntity->getId() . '/invoices/new', $invalidValues);
        self::assertResponseStatusCodeSame(422);
        $form['commercial_invoice[issuedOn]'] = '2026-09-01';
        $client->submit($form);
        self::assertResponseRedirects();

        $invoiceEntity = static::getContainer()->get(EntityManagerInterface::class)->getRepository(CommercialInvoiceEntity::class)->findOneBy(['reference' => "2026-09 - {$suffix}"]);
        self::assertInstanceOf(CommercialInvoiceEntity::class, $invoiceEntity);
        self::assertSame(31000, $invoiceEntity->getAmountCents());
        self::assertNull($invoiceEntity->getReminderAction());
        self::assertFalse($invoiceEntity->hasPaymentTracking());
        self::assertSame('Trois séances de septembre', $invoiceEntity->getDescription());
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Trois séances de septembre', $client->getResponse()->getContent());
        self::assertStringContainsString('Envoyée', $client->getResponse()->getContent());
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $savedActionEntity = $entityManager->find(CommercialActionEntity::class, $actionId);
        self::assertSame(CommercialActionEntity::STATUS_DONE, $savedActionEntity->getStatus());
    }
}
