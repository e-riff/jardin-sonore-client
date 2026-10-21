<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Commercial\CalendarEventOccurrence;
use App\Infrastructure\Doctrine\Commercial\DoctrineProjectContextReader;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DoctrineProjectContextReaderTest extends KernelTestCase
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

    public function testReturnsBoundedRecentEventsAndOpenActions(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $projectEntity = new CommercialProjectEntity('Ateliers', null, null, new DateTimeImmutable('2026-10-01'));
        for ($index = 1; 12 >= $index; ++$index) {
            $projectEntity->recordEvent('note', new DateTimeImmutable("2026-10-{$index} 10:00:00"), "Note {$index}");
        }
        $projectEntity->recordEvent('initial_message', new DateTimeImmutable('2026-10-13 10:00:00'), 'Raw inbound body');
        $projectEntity->recordEvent('invoice_issued', new DateTimeImmutable('2026-10-14 10:00:00'), 'Invoice reference');
        for ($index = 1; 22 >= $index; ++$index) {
            $actionEntity = new CommercialActionEntity($projectEntity, "Action {$index}", new DateTimeImmutable('2026-10-20'));
            if (1 === $index) {
                $actionEntity->setDueTime('14:00');
            }
            $projectEntity->addAction($actionEntity);
        }
        $appointmentStart = new DateTimeImmutable('2026-10-21 14:00:00');
        $appointmentEntity = new CommercialAppointmentEntity($projectEntity, 'calendar', str_repeat('f', 32), new CalendarEventOccurrence('context-rdv', 'RDV · Crèche', $appointmentStart, $appointmentStart->modify('+1 hour'), null, false, etag: 'v1'), $appointmentStart);
        $entityManager->persist($projectEntity);
        $entityManager->persist($appointmentEntity);
        $entityManager->flush();
        $reader = new DoctrineProjectContextReader(self::getContainer()->get(ManagerRegistry::class), self::getContainer()->get(UrlGeneratorInterface::class));

        $context = $reader->get($projectEntity->getId());

        self::assertSame($projectEntity->getId(), $context['id']);
        self::assertCount(10, $context['recentEvents']);
        self::assertSame('Note 12', $context['recentEvents'][0]['content']);
        self::assertStringNotContainsString('Raw inbound body', json_encode($context, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('Invoice reference', json_encode($context, JSON_THROW_ON_ERROR));
        self::assertCount(20, $context['openActions']);
        self::assertSame('14:00', $context['openActions'][0]['dueTime']);
        self::assertCount(1, $context['appointments']);
        self::assertSame('RDV · Crèche', $context['appointments'][0]['title']);
        self::assertSame(1, $context['appointments'][0]['revision']);
        self::assertStringContainsString('/commercial/projects/', $context['url']);
        self::assertArrayNotHasKey('body', $context);
    }

    public function testUnknownProjectFails(): void
    {
        $reader = new DoctrineProjectContextReader(self::getContainer()->get(ManagerRegistry::class), self::getContainer()->get(UrlGeneratorInterface::class));
        $this->expectException(RuntimeException::class);
        $reader->get(999999999);
    }
}
