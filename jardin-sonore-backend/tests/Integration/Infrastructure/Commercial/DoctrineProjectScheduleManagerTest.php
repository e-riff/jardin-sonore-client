<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Mcp\ProjectScheduleManager;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class DoctrineProjectScheduleManagerTest extends KernelTestCase
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

    public function testCreatesAndUpdatesAnIndicativeTaskOnlyForItsExactDossier(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $projectEntity = new CommercialProjectEntity('Dossier MCP', null, null, new DateTimeImmutable());
        $otherProjectEntity = new CommercialProjectEntity('Autre dossier', null, null, new DateTimeImmutable());
        $entityManager->persist($projectEntity);
        $entityManager->persist($otherProjectEntity);
        $entityManager->flush();
        $manager = self::getContainer()->get(ProjectScheduleManager::class);

        $created = $manager->execute('create_project_task', ['projectId' => $projectEntity->getId(), 'title' => 'Relancer la crèche', 'dueOn' => '2026-10-22', 'dueTime' => '14:00']);
        self::assertSame('14:00', $created['dueTime']);
        self::assertSame('open', $created['status']);
        self::assertCount(0, $entityManager->getRepository(CommercialAppointmentEntity::class)->findAll());

        $updated = $manager->execute('update_project_task', ['projectId' => $projectEntity->getId(), 'taskId' => $created['taskId'], 'revision' => $created['revision'], 'title' => 'Rappeler la direction', 'dueOn' => '2026-10-23', 'dueTime' => null]);
        self::assertSame('Rappeler la direction', $updated['title']);
        self::assertNull($updated['dueTime']);

        try {
            $manager->execute('complete_project_task', ['projectId' => $projectEntity->getId(), 'taskId' => $created['taskId'], 'revision' => $created['revision']]);
            self::fail('A stale task revision must be rejected.');
        } catch (DomainException) {
            self::assertSame('open', $updated['status']);
        }

        $this->expectException(DomainException::class);
        $manager->execute('complete_project_task', ['projectId' => $otherProjectEntity->getId(), 'taskId' => $created['taskId'], 'revision' => $updated['revision']]);
    }
}
