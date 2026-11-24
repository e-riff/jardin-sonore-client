<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialProjectQueue;
use App\Application\Twig\Component\CommercialProjectTable;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Repository\CommercialInterventionDoctrineRepository;
use App\Kernel;
use App\Tests\Support\GeographyFixture;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
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

    public function testActiveOverviewKeepsConfirmedRemindersButHidesConfirmedWithoutTasks(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $organizationEntity = (new OrganizationEntity())->setName("Structure {$suffix}");
        $projects = [];
        foreach (['discussion', 'confirmed', 'priority', 'upcoming'] as $kind) {
            $projectEntity = new CommercialProjectEntity("{$kind} {$suffix}", $organizationEntity, null, new DateTimeImmutable('2026-11-01'));
            if ('discussion' !== $kind) {
                $projectEntity->setStatus(CommercialProjectEntity::STATUS_CONFIRMED);
            }
            if (in_array($kind, ['priority', 'upcoming'], true)) {
                $projectEntity->addAction(new CommercialActionEntity($projectEntity, 'Rappeler', new DateTimeImmutable('priority' === $kind ? '2026-11-17' : '2026-11-20')));
            }
            $entityManager->persist($projectEntity);
            $projects[$kind] = $projectEntity;
        }
        $entityManager->persist($organizationEntity);
        $entityManager->flush();
        $table = new CommercialProjectTable(new CommercialProjectQueue($entityManager), new MockClock('2026-11-18 12:00:00 Europe/Paris'));
        $table->query = $suffix;
        $sections = $table->getSections();
        self::assertSame([$projects['priority']->getId()], array_column($sections['priority'], 'id'));
        self::assertSame([$projects['upcoming']->getId()], array_column($sections['upcoming'], 'id'));
        self::assertSame([$projects['discussion']->getId()], array_column($sections['without_task'], 'id'));
        self::assertSame([], $sections['confirmed']);
        $table->status = 'confirmed';
        $sections = $table->getSections();
        $confirmedIds = array_merge(...array_map(static fn (string $bucket): array => array_column($sections[$bucket], 'id'), ['priority', 'upcoming', 'without_task', 'confirmed']));
        self::assertEqualsCanonicalizing([$projects['confirmed']->getId(), $projects['priority']->getId(), $projects['upcoming']->getId()], $confirmedIds);
    }

    public function testWeekSessionsStartTodayAndExcludeCancelledUnlinkedAndOutsideParisWeek(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Structure semaine');
        $projectEntity = new CommercialProjectEntity('Dossier semaine', $organizationEntity, null, new DateTimeImmutable('2026-10-01'));
        $entityManager->persist($organizationEntity);
        $entityManager->persist($projectEntity);
        $sessions = [];
        foreach ([
            'before' => '2026-10-18 21:59:59 UTC',
            'monday' => '2026-10-18 22:00:00 UTC',
            'past' => '2026-10-20 08:00:00 UTC',
            'cancelled' => '2026-10-21 08:00:00 UTC',
            'unlinked' => '2026-10-22 08:00:00 UTC',
            'today' => '2026-10-24 22:00:00 UTC',
            'sunday' => '2026-10-25 22:59:59 UTC',
            'nextMonday' => '2026-10-25 23:00:00 UTC',
        ] as $key => $date) {
            $startsAt = new DateTimeImmutable($date);
            $interventionEntity = new CommercialInterventionEntity('unlinked' === $key ? null : $projectEntity, 'test', bin2hex(random_bytes(8)), $key, $startsAt, $startsAt->modify('+1 hour'), 'Europe/Paris', 'Salle de musique', '', new DateTimeImmutable());
            if ('cancelled' === $key) {
                $interventionEntity->cancel(new DateTimeImmutable());
            }
            $entityManager->persist($interventionEntity);
            $sessions[$key] = $interventionEntity;
        }
        $entityManager->flush();
        $repository = self::getContainer()->get(CommercialInterventionDoctrineRepository::class);
        $results = array_values(array_filter($repository->forWeek(new DateTimeImmutable('2026-10-25 12:00:00 Europe/Paris')), static fn ($session): bool => $session->getProject()?->getId() === $projectEntity->getId()));
        self::assertSame([$sessions['today']->getId(), $sessions['sunday']->getId()], array_map(static fn ($session): ?int => $session->getId(), $results));
    }

    public function testAProjectAppearsOnceWithItsEarliestOpenTaskAndNewBadge(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Crèche file');
        $projectEntity = new CommercialProjectEntity('Ateliers du printemps', $organizationEntity, null, new DateTimeImmutable('2026-10-12'));
        $projectEntity->addAction(new CommercialActionEntity($projectEntity, 'Envoyer les dates', new DateTimeImmutable('2026-10-17')));
        $timedActionEntity = new CommercialActionEntity($projectEntity, 'Répondre', new DateTimeImmutable('2026-10-12'));
        $timedActionEntity->setDueTime('14:00');
        $projectEntity->addAction($timedActionEntity);
        $olderOrganizationEntity = (new OrganizationEntity())->setName('Crèche ancienne');
        $olderProjectEntity = new CommercialProjectEntity('Ateliers précédents', $olderOrganizationEntity, null, new DateTimeImmutable('2026-10-11'));
        $entityManager->persist($organizationEntity);
        $entityManager->persist($projectEntity);
        $entityManager->persist($olderOrganizationEntity);
        $entityManager->persist($olderProjectEntity);
        $entityManager->flush();

        $rows = (new CommercialProjectQueue($entityManager))->forDate(new DateTimeImmutable('2026-10-13'));
        $matchingRows = array_values(array_filter($rows, static fn ($row): bool => $projectEntity->getId() === $row->id));

        self::assertCount(1, $matchingRows);
        self::assertSame('priority', $matchingRows[0]->bucket);
        self::assertSame('Répondre', $matchingRows[0]->nextActionTitle);
        self::assertSame('14:00', $matchingRows[0]->nextDueTime);
        self::assertSame(1, $matchingRows[0]->extraTaskCount);
        self::assertTrue($matchingRows[0]->isNew);

        $olderMatchingRows = array_values(array_filter($rows, static fn ($row): bool => $olderProjectEntity->getId() === $row->id));
        self::assertCount(1, $olderMatchingRows);
        self::assertFalse($olderMatchingRows[0]->isNew);
    }

    public function testTodaysAppointmentPlacesAProjectInPriorityWithoutCreatingAReminder(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Structure avec rendez-vous du jour');
        $projectEntity = new CommercialProjectEntity('Dossier rendez-vous du jour', $organizationEntity, null, new DateTimeImmutable('2026-10-01'));
        $startsAt = new DateTimeImmutable('2026-10-13 15:30:00', new DateTimeZone('Europe/Paris'));
        $appointmentEntity = new CommercialAppointmentEntity(
            $projectEntity,
            'calendar-test',
            str_repeat('a', 32),
            new CalendarEventOccurrence('appointment-today', 'RDV · Échange avec la direction', $startsAt, $startsAt->modify('+1 hour'), null, false),
            new DateTimeImmutable('2026-10-13 12:00:00'),
        );
        $entityManager->persist($organizationEntity);
        $entityManager->persist($projectEntity);
        $entityManager->persist($appointmentEntity);
        $entityManager->flush();

        $rows = (new CommercialProjectQueue($entityManager))->forDate(new DateTimeImmutable('2026-10-13'));
        $matchingRows = array_values(array_filter($rows, static fn ($row): bool => $projectEntity->getId() === $row->id));

        self::assertCount(1, $matchingRows);
        self::assertSame('priority', $matchingRows[0]->bucket);
        self::assertNull($matchingRows[0]->nextActionId);
        self::assertSame('RDV · Échange avec la direction', $matchingRows[0]->nextActionTitle);
        self::assertSame('2026-10-13', $matchingRows[0]->nextDueOn?->format('Y-m-d'));
        self::assertSame('15:30', $matchingRows[0]->nextDueTime);
        self::assertTrue($matchingRows[0]->nextTaskIsAppointment);
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

    public function testUpcomingProjectsAreGroupedByCalendarWeekAndShowTheirLocation(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $projects = [];
        foreach ([
            'Cette semaine' => '2026-10-17',
            'Lundi prochain' => '2026-10-19',
            'Dimanche prochain' => '2026-10-25',
            'Après la semaine prochaine' => '2026-10-26',
            'Dossier confirmé' => '2026-10-20',
        ] as $title => $dueOn) {
            $organizationEntity = (new OrganizationEntity())->setName("Structure {$title}");
            $projectEntity = new CommercialProjectEntity("{$title} {$suffix}", $organizationEntity, null, new DateTimeImmutable('2026-10-10'));
            $projectEntity->addAction(new CommercialActionEntity($projectEntity, 'Rappeler', new DateTimeImmutable($dueOn)));
            if ('Lundi prochain' === $title) {
                $organizationEntity->getContactDetails()?->addAddressContact((new AddressContactEntity())->setMunicipality(GeographyFixture::municipality($entityManager, 'Saint-Étienne', '42000')));
            }
            if ('Dossier confirmé' === $title) {
                $projectEntity->setStatus(CommercialProjectEntity::STATUS_CONFIRMED);
                $projectEntity->recordEvent('note', new DateTimeImmutable('2026-10-15 14:30:00'), 'Échange confirmé.');
            }
            $entityManager->persist($organizationEntity);
            $entityManager->persist($projectEntity);
            $projects[$title] = $projectEntity;
        }
        $entityManager->flush();

        $table = new CommercialProjectTable(
            new CommercialProjectQueue($entityManager),
            new MockClock(new DateTimeImmutable('2026-10-16 12:00:00')),
        );
        $table->query = $suffix;
        $sections = $table->getSections();

        self::assertSame(
            [$projects['Cette semaine']->getId()],
            array_map(static fn ($row): int => $row->id, $sections['upcoming_periods']['this_week']),
        );
        self::assertSame(
            [$projects['Lundi prochain']->getId(), $projects['Dossier confirmé']->getId(), $projects['Dimanche prochain']->getId()],
            array_map(static fn ($row): int => $row->id, $sections['upcoming_periods']['next_week']),
        );
        self::assertSame(
            [$projects['Après la semaine prochaine']->getId()],
            array_map(static fn ($row): int => $row->id, $sections['upcoming_periods']['later']),
        );
        self::assertSame(
            [],
            array_map(static fn ($row): int => $row->id, $sections['confirmed']),
        );
        self::assertSame(
            '2026-10-15',
            array_values(array_filter($sections['upcoming'], static fn ($row): bool => $projects['Dossier confirmé']->getId() === $row->id))[0]->lastEventAt?->format('Y-m-d'),
        );
        self::assertSame('42000 — Saint-Étienne', array_values(array_filter(
            $sections['upcoming'],
            static fn ($row): bool => $projects['Lundi prochain']->getId() === $row->id,
        ))[0]->organizationLocation);
    }
}
