<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Commercial\CommercialProjectQueue;
use App\Application\Twig\Component\CommercialProjectTable;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialProjectQueueTest extends KernelTestCase
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

    public function testAProjectAppearsOnceWithItsEarliestOpenTaskAndNewBadge(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Crèche file');
        $projectEntity = new CommercialProjectEntity('Ateliers du printemps', $organizationEntity, null, new DateTimeImmutable('2026-10-10'));
        $projectEntity->addAction(new CommercialActionEntity($projectEntity, 'Envoyer les dates', new DateTimeImmutable('2026-10-17')));
        $projectEntity->addAction(new CommercialActionEntity($projectEntity, 'Répondre', new DateTimeImmutable('2026-10-12')));
        $entityManager->persist($organizationEntity);
        $entityManager->persist($projectEntity);
        $entityManager->flush();

        $rows = (new CommercialProjectQueue($entityManager))->forDate(new DateTimeImmutable('2026-10-13'));
        $matchingRows = array_values(array_filter($rows, static fn ($row): bool => $projectEntity->getId() === $row->id));

        self::assertCount(1, $matchingRows);
        self::assertSame('priority', $matchingRows[0]->bucket);
        self::assertSame('Répondre', $matchingRows[0]->nextActionTitle);
        self::assertSame(1, $matchingRows[0]->extraTaskCount);
        self::assertTrue($matchingRows[0]->isNew);
    }

    public function testClosedProjectsAreHiddenByDefaultAndCanBeIncluded(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Structure close');
        $projectEntity = new CommercialProjectEntity('Dossier clos', $organizationEntity, null, new DateTimeImmutable('2026-09-01'));
        $projectEntity->setStatus(CommercialProjectEntity::STATUS_WITHOUT_RESULT, new DateTimeImmutable('2026-09-02'));
        $entityManager->persist($organizationEntity);
        $entityManager->persist($projectEntity);
        $entityManager->flush();
        $queue = new CommercialProjectQueue($entityManager);
        $today = new DateTimeImmutable('2026-10-13');

        self::assertCount(0, array_filter($queue->forDate($today), static fn ($row): bool => $projectEntity->getId() === $row->id));
        self::assertCount(1, array_filter($queue->forDate($today, true), static fn ($row): bool => $projectEntity->getId() === $row->id));
    }

    public function testLiveTableFiltersByStructureAndStatus(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $organizationEntity = (new OrganizationEntity())->setName("École {$suffix}");
        $openProjectEntity = new CommercialProjectEntity('Ateliers', $organizationEntity, null, new DateTimeImmutable());
        $closedProjectEntity = new CommercialProjectEntity('Ateliers terminés', $organizationEntity, null, new DateTimeImmutable());
        $closedProjectEntity->setStatus(CommercialProjectEntity::STATUS_COMPLETED, new DateTimeImmutable());
        foreach ([$organizationEntity, $openProjectEntity, $closedProjectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $table = self::getContainer()->get(CommercialProjectTable::class);
        self::assertInstanceOf(CommercialProjectTable::class, $table);
        $table->query = $suffix;
        $sections = $table->getSections();
        self::assertSame([$openProjectEntity->getId()], array_map(static fn ($row): int => $row->id, $sections['without_task']));
        self::assertSame([], $sections['closed']);

        $table->status = 'all';
        $sections = $table->getSections();
        self::assertSame([$closedProjectEntity->getId()], array_map(static fn ($row): int => $row->id, $sections['closed']));
    }
}
