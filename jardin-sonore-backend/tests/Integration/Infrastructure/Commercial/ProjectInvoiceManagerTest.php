<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Commercial\PrepareCommercialInvoices;
use App\Infrastructure\Doctrine\Commercial\DoctrineProjectInvoiceManager;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoicePreparationEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\KernelInterface;

final class ProjectInvoiceManagerTest extends KernelTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testRecordingSentInvoiceClosesPreparationAndIsIdempotent(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $organizationEntity = (new OrganizationEntity())->setName('Structure facture MCP');
            $projectEntity = new CommercialProjectEntity('Dossier facture MCP', $organizationEntity, null, new DateTimeImmutable());
            $projectEntity->setStatus(CommercialProjectEntity::STATUS_CONFIRMED);
            $actionEntity = new CommercialActionEntity($projectEntity, 'Préparer la facture', new DateTimeImmutable('2026-11-15'));
            $projectEntity->addAction($actionEntity);
            $preparationEntity = new CommercialInvoicePreparationEntity($projectEntity, new DateTimeImmutable('2026-11-01'), $actionEntity);
            $startsAt = new DateTimeImmutable('2026-11-15 09:00:00 Europe/Paris');
            $interventionEntity = new CommercialInterventionEntity($projectEntity, 'test', bin2hex(random_bytes(8)), 'Atelier', $startsAt, $startsAt->modify('+1 hour'), 'Europe/Paris', null, '', new DateTimeImmutable());
            $interventionEntity->confirm();
            foreach ([$organizationEntity, $projectEntity, $preparationEntity, $interventionEntity] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();
            $manager = self::getContainer()->get(DoctrineProjectInvoiceManager::class);
            $arguments = ['projectId' => $projectEntity->getId(), 'reference' => 'MCP-' . bin2hex(random_bytes(8)), 'amount' => '120,50', 'description' => 'Deux séances', 'sentOn' => '2026-11-19', 'serviceMonth' => '2026-11'];
            self::assertCount(1, $manager->list($projectEntity->getId())['todo']);
            $result = $manager->record($arguments);
            self::assertSame($result, $manager->record($arguments));
            (new PrepareCommercialInvoices($entityManager, new MockClock('2026-11-19 12:00:00 Europe/Paris')))->run();
            $list = $manager->list($projectEntity->getId());
            self::assertSame([], $list['todo']);
            self::assertCount(1, $list['sent']);
            self::assertSame('Deux séances', $list['sent'][0]['description']);
            self::assertSame(12050, $list['sent'][0]['amountCents']);
            $arguments['amount'] = '121';
            $this->expectException(DomainException::class);
            $manager->record($arguments);
        } finally {
            $connection->rollBack();
        }
    }
}
