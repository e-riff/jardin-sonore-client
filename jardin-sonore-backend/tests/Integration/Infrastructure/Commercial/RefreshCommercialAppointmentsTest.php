<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialCalendarGateway;
use App\Application\Commercial\RefreshCommercialAppointments;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Google\GoogleCalendarApiException;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RefreshCommercialAppointmentsTest extends KernelTestCase
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

    public function testGoogleEditAndDeletionKeepOneAppointmentAndItsSummary(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $now = new DateTimeImmutable('2026-10-20 10:00:00');
        $projectEntity = new CommercialProjectEntity('Crèche', null, null, $now);
        $start = new DateTimeImmutable('2026-10-22 14:00:00');
        $original = new CalendarEventOccurrence('event-refresh', 'RDV · Crèche', $start, $start->modify('+1 hour'), null, false, etag: 'v1');
        $appointmentEntity = new CommercialAppointmentEntity($projectEntity, 'calendar', str_repeat('a', 32), $original, $now);
        $appointmentEntity->setSummary('Accord sur le projet');
        $entityManager->persist($projectEntity);
        $entityManager->persist($appointmentEntity);
        $entityManager->flush();

        $changed = new CalendarEventOccurrence('event-refresh', 'Échange avec la direction', $start->modify('+1 day'), $start->modify('+1 day +1 hour'), 'Crèche', false, etag: 'v2');
        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->expects(self::exactly(2))->method('event')->with('event-refresh')->willReturnOnConsecutiveCalls($changed, null);
        $refresher = new RefreshCommercialAppointments($entityManager, $calendarGateway, new MockClock($now), self::getContainer()->get(TranslatorInterface::class));

        self::assertSame(1, $refresher->run($projectEntity->getId()));
        self::assertSame('Échange avec la direction', $appointmentEntity->getTitle());
        self::assertSame(3, $appointmentEntity->getRevision());
        self::assertSame(1, $refresher->run($projectEntity->getId()));
        self::assertSame(CommercialAppointmentEntity::STATUS_CANCELED, $appointmentEntity->getStatus());
        self::assertSame('Accord sur le projet', $appointmentEntity->getSummary());
        self::assertCount(2, $projectEntity->getEvents());
        self::assertCount(1, $entityManager->getRepository(CommercialAppointmentEntity::class)->findAll());
    }

    public function testGoogleFailureMarksTheScheduleUncertainWithoutCancelingIt(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $now = new DateTimeImmutable('2026-10-20 10:00:00');
        $projectEntity = new CommercialProjectEntity('Crèche', null, null, $now);
        $start = new DateTimeImmutable('2026-10-22 14:00:00');
        $appointmentEntity = new CommercialAppointmentEntity($projectEntity, 'calendar', str_repeat('b', 32), new CalendarEventOccurrence('event-failure', 'RDV · Crèche', $start, $start->modify('+1 hour'), null, false), $now);
        $entityManager->persist($projectEntity);
        $entityManager->persist($appointmentEntity);
        $entityManager->flush();
        $calendarGateway = $this->createStub(CommercialCalendarGateway::class);
        $calendarGateway->method('event')->willThrowException(new GoogleCalendarApiException('offline'));
        $refresher = new RefreshCommercialAppointments($entityManager, $calendarGateway, new MockClock($now), self::getContainer()->get(TranslatorInterface::class));

        self::assertSame(0, $refresher->run($projectEntity->getId()));
        self::assertSame(CommercialAppointmentEntity::STATUS_SCHEDULED, $appointmentEntity->getStatus());
        self::assertSame('google_sync_failed', $appointmentEntity->getSyncError());
    }

    public function testGlobalRefreshFindsAnOldAppointmentMovedToTodayInGoogle(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $now = new DateTimeImmutable('2026-10-20 10:00:00');
        $projectEntity = new CommercialProjectEntity('Crèche', null, null, $now);
        $oldStart = new DateTimeImmutable('2026-08-20 14:00:00');
        $appointmentEntity = new CommercialAppointmentEntity($projectEntity, 'calendar', str_repeat('c', 32), new CalendarEventOccurrence('event-old', 'RDV · Crèche', $oldStart, $oldStart->modify('+1 hour'), null, false, etag: 'v1'), $now);
        $entityManager->persist($projectEntity);
        $entityManager->persist($appointmentEntity);
        $entityManager->flush();

        $newStart = new DateTimeImmutable('2026-10-20 14:00:00');
        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->expects(self::once())->method('event')->with('event-old')->willReturn(new CalendarEventOccurrence('event-old', 'RDV · Crèche', $newStart, $newStart->modify('+1 hour'), null, false, etag: 'v2'));
        $refresher = new RefreshCommercialAppointments($entityManager, $calendarGateway, new MockClock($now), self::getContainer()->get(TranslatorInterface::class));

        self::assertSame(1, $refresher->run());
        self::assertSame($newStart->getTimestamp(), $appointmentEntity->getStartsAt()->getTimestamp());
    }
}
