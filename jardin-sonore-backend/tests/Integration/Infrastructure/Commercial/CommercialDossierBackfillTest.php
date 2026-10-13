<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Command\BackfillCommercialRequestProjectsCommand;
use App\Infrastructure\Commercial\BackfillCommercialRequestProjects;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialQuoteEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialDossierBackfillTest extends KernelTestCase
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

    public function testAnUnlinkedOpenRequestBecomesOneProvisionalDossierWithReviewTask(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $requestEntity = new CommercialRequestEntity(
            'site',
            'Camille Martin',
            'camille@example.test',
            'Le texte original de la demande',
            new DateTimeImmutable('2026-10-09 08:00:00'),
            'Crèche des Lilas',
            'Mornant',
            '06 12 34 56 78',
        );
        $entityManager->persist($requestEntity);
        $entityManager->flush();
        $requestId = $requestEntity->getId();
        $backfill = new BackfillCommercialRequestProjects($entityManager, new MockClock('2026-10-13 10:00:00 Europe/Paris'));

        self::assertSame(1, $backfill->run([$requestId]));
        self::assertSame(0, $backfill->run([$requestId]));
        $entityManager->clear();

        $reloadedRequestEntity = $entityManager->find(CommercialRequestEntity::class, $requestId);
        self::assertInstanceOf(CommercialRequestEntity::class, $reloadedRequestEntity);
        self::assertSame('Le texte original de la demande', $reloadedRequestEntity->getMessage());
        self::assertSame('camille@example.test', $reloadedRequestEntity->getEmailAddress());
        self::assertSame('06 12 34 56 78', $reloadedRequestEntity->getPhone());
        self::assertCount(1, $reloadedRequestEntity->getProjects());
        $projectEntity = $reloadedRequestEntity->getProjects()->first();
        self::assertInstanceOf(CommercialProjectEntity::class, $projectEntity);
        self::assertNull($projectEntity->getOrganization());
        self::assertSame('discussion', $projectEntity->getStatus());
        self::assertCount(1, $projectEntity->getActions());
        self::assertSame('Vérifier la suite de ce contact', $projectEntity->getActions()->first()->getTitle());
        self::assertSame('2026-10-13', $projectEntity->getActions()->first()->getDueOn()->format('Y-m-d'));
        self::assertCount(0, $projectEntity->getEvents());
    }

    public function testClosedRequestBecomesClosedDossierWithoutInventedTask(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $requestEntity = new CommercialRequestEntity('manual', 'Ancien contact', '', 'Historique', new DateTimeImmutable('2026-09-01 10:00:00'));
        $requestEntity->closeWithoutResult();
        $entityManager->persist($requestEntity);
        $entityManager->flush();
        $requestId = $requestEntity->getId();
        $backfill = new BackfillCommercialRequestProjects($entityManager, new MockClock('2026-10-13 10:00:00 Europe/Paris'));

        self::assertSame(1, $backfill->run([$requestId]));
        $entityManager->clear();

        $projectEntity = $entityManager->find(CommercialRequestEntity::class, $requestId)->getProjects()->first();
        self::assertSame('without_result', $projectEntity->getStatus());
        self::assertCount(0, $projectEntity->getActions());
    }

    public function testLinkedRequestKeepsItsExistingProjectsWithoutCopies(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Mairie de Mornant');
        $requestEntity = new CommercialRequestEntity('manual', 'Camille Martin', '', 'Demande ancienne', new DateTimeImmutable('2026-09-01 10:00:00'));
        $firstProjectEntity = new CommercialProjectEntity('Premier dossier', $organizationEntity, $requestEntity, new DateTimeImmutable('2026-09-02 10:00:00'));
        $secondProjectEntity = new CommercialProjectEntity('Second dossier', $organizationEntity, $requestEntity, new DateTimeImmutable('2026-09-03 10:00:00'));
        $requestEntity->addProject($firstProjectEntity);
        $requestEntity->addProject($secondProjectEntity);
        $entityManager->persist($organizationEntity);
        $entityManager->persist($requestEntity);
        $entityManager->flush();
        $requestId = $requestEntity->getId();
        $firstProjectId = $firstProjectEntity->getId();
        $backfill = new BackfillCommercialRequestProjects($entityManager, new MockClock('2026-10-13 10:00:00 Europe/Paris'));

        self::assertSame(0, $backfill->run([$requestId]));
        $entityManager->clear();

        $reloadedRequestEntity = $entityManager->find(CommercialRequestEntity::class, $requestId);
        self::assertCount(2, $reloadedRequestEntity->getProjects());
        self::assertSame($firstProjectId, $backfill->canonicalProjectId($requestId));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM commercial_project WHERE request_id = ?', [$requestId]));
    }

    public function testQuoteRefusalAndEventSourceSurviveReload(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Crèche des Lilas');
        $projectEntity = new CommercialProjectEntity('Ateliers', $organizationEntity, null, new DateTimeImmutable('2026-10-09 08:00:00'));
        $quoteEntity = new CommercialQuoteEntity($projectEntity, 'D-2026-01', 'devis.pdf', 120000, new DateTimeImmutable('2026-10-09'));
        $quoteEntity->markRefused(new DateTimeImmutable('2026-10-12'));
        $projectEntity->addQuote($quoteEntity);
        $actionEntity = new CommercialActionEntity($projectEntity, 'Répondre', new DateTimeImmutable('2026-10-13'));
        $projectEntity->addAction($actionEntity);
        $quoteEventEntity = $projectEntity->recordEvent('quote_refused', new DateTimeImmutable('2026-10-12 09:00:00'), 'Refus reçu');
        $quoteEventEntity->linkQuote($quoteEntity);
        $actionEventEntity = $projectEntity->recordEvent('action_completed', new DateTimeImmutable('2026-10-13 10:00:00'));
        $actionEventEntity->linkAction($actionEntity);
        $entityManager->persist($organizationEntity);
        $entityManager->persist($projectEntity);
        $entityManager->flush();
        $projectId = $projectEntity->getId();
        $entityManager->clear();

        $reloadedProjectEntity = $entityManager->find(CommercialProjectEntity::class, $projectId);
        self::assertInstanceOf(CommercialProjectEntity::class, $reloadedProjectEntity);
        self::assertSame('2026-10-12', $reloadedProjectEntity->getQuotes()->first()->getRefusedOn()->format('Y-m-d'));
        self::assertSame('D-2026-01', $reloadedProjectEntity->getEvents()->first()->getQuote()->getReference());
        self::assertSame('Répondre', $reloadedProjectEntity->getEvents()->last()->getAction()->getTitle());
    }

    public function testBackfillCommandReportsCreatedCountAndCanBeReplayed(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new CommercialRequestEntity('manual', 'Camille', '', 'Demande', new DateTimeImmutable('2026-10-09 08:00:00')));
        $entityManager->flush();
        $pendingCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM commercial_request request WHERE NOT EXISTS (SELECT 1 FROM commercial_project project WHERE project.request_id = request.id)');
        $backfill = new BackfillCommercialRequestProjects($entityManager, new MockClock('2026-10-13 10:00:00 Europe/Paris'));
        $commandTester = new CommandTester(new BackfillCommercialRequestProjectsCommand($backfill));

        self::assertSame(0, $commandTester->execute([]));
        self::assertStringContainsString("{$pendingCount} dossier", $commandTester->getDisplay());
        self::assertSame(0, $commandTester->execute([]));
        self::assertStringContainsString('0 dossier', $commandTester->getDisplay());
    }
}
