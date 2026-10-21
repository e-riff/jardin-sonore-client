<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Commercial\CalendarEventDraft;
use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialAppointmentManager;
use App\Application\Commercial\CommercialCalendarGateway;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Google\GoogleCalendarConflictException;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CommercialAppointmentManagerTest extends KernelTestCase
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

    public function testCreateIsIdempotentAndCannotReuseARequestForAnotherDossier(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $now = new DateTimeImmutable('2026-10-20 10:00:00');
        $organizationEntity = (new OrganizationEntity())->setName('Crèche RDV');
        $projectEntity = new CommercialProjectEntity('Dossier A', $organizationEntity, null, $now);
        $otherProjectEntity = new CommercialProjectEntity('Dossier B', $organizationEntity, null, $now);
        foreach ([$organizationEntity, $projectEntity, $otherProjectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $start = new DateTimeImmutable('2026-10-21 14:00:00');
        $end = $start->modify('+1 hour');
        $requestId = str_repeat('b', 32);
        $googleRequestId = substr(hash('sha256', $projectEntity->getId() . ':' . $requestId), 0, 32);
        $draft = new CalendarEventDraft('Échange avec la direction', $start, $end, 'Europe/Paris', null);
        $googleEvent = new CalendarEventOccurrence($googleRequestId, 'RDV · Échange avec la direction', $start, $end, null, false, etag: 'v1');
        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->expects(self::once())->method('createEvent')->with(self::callback(static fn (CalendarEventDraft $createdDraft): bool => 'RDV · Échange avec la direction' === $createdDraft->title), $googleRequestId)->willReturn($googleEvent);
        $manager = new CommercialAppointmentManager($entityManager, $calendarGateway, new MockClock($now), 'calendar', self::getContainer()->get(TranslatorInterface::class));

        $appointmentEntity = $manager->create($projectEntity, $draft, $requestId);
        self::assertSame($appointmentEntity, $manager->create($projectEntity, $draft, $requestId));
        self::assertCount(1, $entityManager->getRepository(CommercialAppointmentEntity::class)->findAll());
        self::assertSame('RDV · Échange avec la direction', $appointmentEntity->getTitle());

        $this->expectException(DomainException::class);
        $manager->create($otherProjectEntity, $draft, $requestId);
    }

    public function testGoogleChangeRejectsAnOutdatedEditAndKeepsTheSummary(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $now = new DateTimeImmutable('2026-10-20 10:00:00');
        $organizationEntity = (new OrganizationEntity())->setName('Crèche RDV');
        $projectEntity = new CommercialProjectEntity('Dossier', $organizationEntity, null, $now);
        $entityManager->persist($organizationEntity);
        $entityManager->persist($projectEntity);
        $start = new DateTimeImmutable('2026-10-21 14:00:00');
        $end = $start->modify('+1 hour');
        $event = new CalendarEventOccurrence('event-1', 'RDV · Crèche', $start, $end, null, false, etag: 'v1');
        $appointmentEntity = new CommercialAppointmentEntity($projectEntity, 'calendar', str_repeat('c', 32), $event, $now);
        $appointmentEntity->setSummary('Compte rendu');
        $entityManager->persist($appointmentEntity);
        $entityManager->flush();

        $changedEvent = new CalendarEventOccurrence('event-1', 'RDV · Crèche déplacé', $start->modify('+1 day'), $end->modify('+1 day'), null, false, etag: 'v2');
        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->expects(self::once())->method('event')->with('event-1')->willReturn($changedEvent);
        $calendarGateway->expects(self::never())->method('updateEvent');
        $manager = new CommercialAppointmentManager($entityManager, $calendarGateway, new MockClock($now), 'calendar', self::getContainer()->get(TranslatorInterface::class));

        try {
            $manager->update($appointmentEntity, new CalendarEventDraft('Nouveau titre', $start, $end, 'Europe/Paris', null), 2);
            self::fail('An outdated Google version must be rejected.');
        } catch (GoogleCalendarConflictException) {
            self::assertSame('RDV · Crèche déplacé', $appointmentEntity->getTitle());
            self::assertSame('Compte rendu', $appointmentEntity->getSummary());
            self::assertSame(3, $appointmentEntity->getRevision());
        }
    }

    public function testCreateRejectsATitleTooLongAfterTheCalendarMarkerBeforeCallingGoogle(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $now = new DateTimeImmutable('2026-10-20 10:00:00');
        $organizationEntity = (new OrganizationEntity())->setName('Crèche RDV');
        $projectEntity = new CommercialProjectEntity('Dossier', $organizationEntity, null, $now);
        $entityManager->persist($organizationEntity);
        $entityManager->persist($projectEntity);
        $entityManager->flush();

        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->expects(self::never())->method('createEvent');
        $manager = new CommercialAppointmentManager($entityManager, $calendarGateway, new MockClock($now), 'calendar', self::getContainer()->get(TranslatorInterface::class));
        $start = new DateTimeImmutable('2026-10-21 14:00:00');

        $this->expectException(DomainException::class);
        $manager->create($projectEntity, new CalendarEventDraft(str_repeat('a', 250), $start, $start->modify('+1 hour'), 'Europe/Paris', null), str_repeat('d', 32));
    }

    public function testSummaryCannotOverwriteAConcurrentRevision(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $now = new DateTimeImmutable('2026-10-20 10:00:00');
        $projectEntity = new CommercialProjectEntity('Dossier', null, null, $now);
        $start = new DateTimeImmutable('2026-10-21 14:00:00');
        $appointmentEntity = new CommercialAppointmentEntity($projectEntity, 'calendar', str_repeat('e', 32), new CalendarEventOccurrence('event-concurrent', 'RDV · Crèche', $start, $start->modify('+1 hour'), null, false), $now);
        $entityManager->persist($projectEntity);
        $entityManager->persist($appointmentEntity);
        $entityManager->flush();
        $this->connection->executeStatement('UPDATE commercial_appointment SET revision = revision + 1, summary = ? WHERE id = ?', ['Premier compte rendu', $appointmentEntity->getId()]);

        $calendarGateway = $this->createStub(CommercialCalendarGateway::class);
        $manager = new CommercialAppointmentManager($entityManager, $calendarGateway, new MockClock($now), 'calendar', self::getContainer()->get(TranslatorInterface::class));

        $this->expectException(GoogleCalendarConflictException::class);
        $manager->setSummary($appointmentEntity, 'Écrasement concurrent', 1);
    }

    public function testSummaryCanBeClearedWithTheCurrentRevision(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $now = new DateTimeImmutable('2026-10-20 10:00:00');
        $projectEntity = new CommercialProjectEntity('Dossier', null, null, $now);
        $start = new DateTimeImmutable('2026-10-21 14:00:00');
        $appointmentEntity = new CommercialAppointmentEntity($projectEntity, 'calendar', str_repeat('f', 32), new CalendarEventOccurrence('event-summary', 'RDV · Crèche', $start, $start->modify('+1 hour'), null, false), $now);
        $entityManager->persist($projectEntity);
        $entityManager->persist($appointmentEntity);
        $entityManager->flush();

        $manager = new CommercialAppointmentManager($entityManager, $this->createStub(CommercialCalendarGateway::class), new MockClock($now), 'calendar', self::getContainer()->get(TranslatorInterface::class));
        $manager->setSummary($appointmentEntity, 'Premier compte rendu', 1);
        self::assertSame(2, $appointmentEntity->getRevision());
        $manager->setSummary($appointmentEntity, null, 2);
        self::assertNull($appointmentEntity->getSummary());
        self::assertSame(3, $appointmentEntity->getRevision());
        self::assertNull($this->connection->fetchOne('SELECT summary FROM commercial_appointment WHERE id = ?', [$appointmentEntity->getId()]));
    }
}
