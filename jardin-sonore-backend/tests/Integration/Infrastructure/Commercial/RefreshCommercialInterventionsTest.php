<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialCalendarGateway;
use App\Application\Commercial\RefreshCommercialInterventions;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RefreshCommercialInterventionsTest extends KernelTestCase
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

    public function testGoogleOptionMarkerChangesTheLinkedSessionStatusInBothDirections(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $now = new DateTimeImmutable('2026-10-20 10:00:00');
        $projectEntity = new CommercialProjectEntity('Crèche', null, null, $now);
        $startsAt = new DateTimeImmutable('2026-10-22 14:00:00');
        $interventionEntity = new CommercialInterventionEntity($projectEntity, 'calendar', 'event-option-sync', 'Atelier', $startsAt, $startsAt->modify('+1 hour'), 'Europe/Paris', null, 'v1', $now);
        $entityManager->persist($projectEntity);
        $entityManager->persist($interventionEntity);
        $entityManager->flush();

        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->expects(self::exactly(2))->method('event')->with('event-option-sync')->willReturnOnConsecutiveCalls(
            new CalendarEventOccurrence('event-option-sync', 'Atelier', $startsAt, $startsAt->modify('+1 hour'), null, false, etag: 'v2'),
            new CalendarEventOccurrence('event-option-sync', '[OPT] Atelier', $startsAt, $startsAt->modify('+1 hour'), null, false, etag: 'v3'),
        );
        $refresher = new RefreshCommercialInterventions($entityManager, $calendarGateway, new MockClock($now), self::getContainer()->get(TranslatorInterface::class));

        self::assertSame(1, $refresher->run($projectEntity->getId()));
        self::assertSame(CommercialInterventionEntity::STATUS_CONFIRMED, $interventionEntity->getStatus());
        self::assertSame(1, $refresher->run($projectEntity->getId()));
        self::assertSame(CommercialInterventionEntity::STATUS_OPTION, $interventionEntity->getStatus());
        self::assertSame('Atelier', $interventionEntity->getTitle());
    }
}
