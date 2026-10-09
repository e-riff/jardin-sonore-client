<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Commercial\BuildCommercialDigest;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

final class BuildCommercialDigestTest extends KernelTestCase
{
    private Connection $connection;

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testMondayIncludesRequestsAndProjectsWithoutActionButNoDuplicateInvoiceReminder(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Structure digest');
        $requestEntity = new CommercialRequestEntity('manual', 'Contact digest', '', 'Nouvelle demande', new DateTimeImmutable('2026-10-09'));
        $projectWithoutActionEntity = new CommercialProjectEntity('Sans action', $organizationEntity, null, new DateTimeImmutable('2026-10-09'));
        $projectWithInvoiceEntity = new CommercialProjectEntity('Avec facture', $organizationEntity, null, new DateTimeImmutable('2026-10-09'));
        $projectWithInvoiceEntity->setStatus(CommercialProjectEntity::STATUS_CONFIRMED);
        $invoiceEntity = new CommercialInvoiceEntity($projectWithInvoiceEntity, 'DIG-' . bin2hex(random_bytes(5)), 10000, new DateTimeImmutable('2026-09-01'));
        $reminderActionEntity = new CommercialActionEntity($projectWithInvoiceEntity, 'Relancer facture', new DateTimeImmutable('2026-10-01'));
        $invoiceEntity->setReminderAction($reminderActionEntity);
        $projectWithInvoiceEntity->addInvoice($invoiceEntity);
        $projectWithInvoiceEntity->addAction($reminderActionEntity);
        foreach ([$organizationEntity, $requestEntity, $projectWithoutActionEntity, $projectWithInvoiceEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $builder = new BuildCommercialDigest($entityManager);
        $mondayDigest = $builder->forDate(new DateTimeImmutable('2026-10-12'));
        self::assertContains($requestEntity, $mondayDigest->requests);
        self::assertContains($projectWithoutActionEntity, $mondayDigest->withoutAction);
        self::assertContains($invoiceEntity, $mondayDigest->overdueInvoices);
        self::assertNotContains($reminderActionEntity, $mondayDigest->dueActions);

        $twig = self::getContainer()->get(Environment::class);
        $html = $twig->render('commercial/digest_email.html.twig', ['digest' => $mondayDigest]);
        $text = $twig->render('commercial/digest_email.txt.twig', ['digest' => $mondayDigest]);
        self::assertStringContainsString('/commercial/requests/' . $requestEntity->getId(), $html);
        self::assertStringContainsString('/commercial/requests/' . $requestEntity->getId(), $text);
        self::assertStringNotContainsString('Relancer facture', $html);

        $tuesdayDigest = $builder->forDate(new DateTimeImmutable('2026-10-13'));
        self::assertSame([], $tuesdayDigest->withoutAction);
    }
}
