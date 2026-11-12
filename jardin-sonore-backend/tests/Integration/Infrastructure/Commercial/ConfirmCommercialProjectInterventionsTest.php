<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Commercial\CalendarEventDraft;
use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialCalendarGateway;
use App\Application\Commercial\CommercialProjectWorkflow;
use App\Application\Commercial\ConfirmCommercialProjectInterventions;
use App\Application\Commercial\RecordCommercialQuote;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialQuoteEntity;
use App\Infrastructure\Doctrine\Repository\CommercialInterventionDoctrineRepository;
use App\Infrastructure\Google\GoogleCalendarConflictException;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\KernelInterface;

final class ConfirmCommercialProjectInterventionsTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel('test', true);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertStringEndsWith('_test', (string) $this->entityManager->getConnection()->getDatabase());
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSigningRetryOnlyProcessesRemainingOptionsAndPreservesFreshGoogleSchedule(): void
    {
        [$commercialProjectEntity, $commercialQuoteEntity, $firstInterventionEntity] = $this->fixtures();
        $secondInterventionEntity = new CommercialInterventionEntity($commercialProjectEntity, 'calendar', 'second-' . bin2hex(random_bytes(5)), 'Second atelier', $firstInterventionEntity->getStartsAt(), $firstInterventionEntity->getEndsAt(), 'Europe/Paris', null, 'old', new DateTimeImmutable());
        $this->entityManager->persist($secondInterventionEntity);
        $this->entityManager->flush();
        $freshStart = $firstInterventionEntity->getStartsAt()->modify('+2 hours');
        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->method('event')->willReturnCallback(static fn (string $id): CalendarEventOccurrence => new CalendarEventOccurrence($id, '[OPT] Atelier', $freshStart, $freshStart->modify('+1 hour'), 'Nouvelle salle', false, etag: 'fresh'));
        $failedOnce = false;
        $calendarGateway->expects(self::exactly(3))->method('updateEvent')->willReturnCallback(static function (CalendarEventOccurrence $event, CalendarEventDraft $draft) use ($secondInterventionEntity, $freshStart, &$failedOnce): CalendarEventOccurrence {
            self::assertSame('Atelier', $draft->title);
            self::assertSame($freshStart, $draft->startsAt);
            self::assertSame('Nouvelle salle', $draft->location);
            self::assertSame('fresh', $event->etag);
            if ($event->id === $secondInterventionEntity->getGoogleEventId() && !$failedOnce) {
                $failedOnce = true;
                throw new GoogleCalendarConflictException('Concurrent Google edit');
            }

            return new CalendarEventOccurrence($event->id, $draft->title, $draft->startsAt, $draft->endsAt, $draft->location, false, etag: 'updated');
        });
        $quoteRecorder = $this->recorder($calendarGateway);
        self::assertSame(['confirmed' => 1, 'failed' => 1], $quoteRecorder->markSigned($commercialQuoteEntity, new DateTimeImmutable('today')));
        $this->entityManager->flush();
        self::assertSame(CommercialProjectEntity::STATUS_CONFIRMED, $commercialProjectEntity->getStatus());
        self::assertSame(CommercialInterventionEntity::STATUS_CONFIRMED, $firstInterventionEntity->getStatus());
        self::assertSame(CommercialInterventionEntity::STATUS_OPTION, $secondInterventionEntity->getStatus());
        self::assertSame('google_version_conflict', $secondInterventionEntity->getSyncError());
        self::assertSame(['confirmed' => 1, 'failed' => 0], $quoteRecorder->markSigned($commercialQuoteEntity, new DateTimeImmutable('today')));
        $this->entityManager->flush();
        self::assertSame(CommercialInterventionEntity::STATUS_CONFIRMED, $secondInterventionEntity->getStatus());
        self::assertNull($secondInterventionEntity->getSyncError());
        self::assertCount(1, $commercialProjectEntity->getEvents()->filter(static fn ($event): bool => 'quote_signed' === $event->getType()));
    }

    /** @return iterable<string, array{string}> */
    public static function unavailableEvents(): iterable
    {
        yield 'missing' => ['missing'];
        yield 'canceled in Google' => ['canceled'];
        yield 'converted to all day' => ['all_day'];
        yield 'marker not removed by Google response' => ['marker_retained'];
    }

    #[DataProvider('unavailableEvents')]
    public function testFailedCalendarConfirmationKeepsTheSessionAsOption(string $case): void
    {
        [, $commercialQuoteEntity, $interventionEntity] = $this->fixtures();
        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $event = new CalendarEventOccurrence($interventionEntity->getGoogleEventId(), '[OPT] Atelier', $interventionEntity->getStartsAt(), $interventionEntity->getEndsAt(), null, 'all_day' === $case, etag: 'fresh', googleStatus: 'canceled' === $case ? 'cancelled' : 'confirmed');
        $calendarGateway->method('event')->willReturn('missing' === $case ? null : $event);
        $calendarGateway->expects('marker_retained' === $case ? self::once() : self::never())->method('updateEvent')->willReturn($event);
        self::assertSame(['confirmed' => 0, 'failed' => 1], $this->recorder($calendarGateway)->markSigned($commercialQuoteEntity, new DateTimeImmutable('today')));
        self::assertNotNull($commercialQuoteEntity->getSignedOn());
        self::assertSame(CommercialInterventionEntity::STATUS_OPTION, $interventionEntity->getStatus());
        self::assertNotNull($interventionEntity->getSyncError());
    }

    public function testAlreadyUnmarkedGoogleEventNeedsNoPatch(): void
    {
        [, $commercialQuoteEntity, $interventionEntity] = $this->fixtures();
        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->method('event')->willReturn(new CalendarEventOccurrence($interventionEntity->getGoogleEventId(), 'Atelier', $interventionEntity->getStartsAt(), $interventionEntity->getEndsAt(), null, false, etag: 'fresh'));
        $calendarGateway->expects(self::never())->method('updateEvent');
        self::assertSame(['confirmed' => 1, 'failed' => 0], $this->recorder($calendarGateway)->markSigned($commercialQuoteEntity, new DateTimeImmutable('today')));
        self::assertSame('fresh', $interventionEntity->getEtag());
    }

    /** @return array{CommercialProjectEntity, CommercialQuoteEntity, CommercialInterventionEntity} */
    private function fixtures(): array
    {
        $suffix = bin2hex(random_bytes(5));
        $commercialProjectEntity = new CommercialProjectEntity('Ateliers', null, null, new DateTimeImmutable());
        $commercialQuoteEntity = new CommercialQuoteEntity($commercialProjectEntity, "CAL-{$suffix}", 'devis.pdf', 25000, new DateTimeImmutable('today'));
        $commercialProjectEntity->addQuote($commercialQuoteEntity);
        $startsAt = new DateTimeImmutable('+7 days 10:00:00');
        $interventionEntity = new CommercialInterventionEntity($commercialProjectEntity, 'calendar', "first-{$suffix}", 'Atelier', $startsAt, $startsAt->modify('+1 hour'), 'Europe/Paris', null, 'old', new DateTimeImmutable());
        foreach ([$commercialProjectEntity, $commercialQuoteEntity, $interventionEntity] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        return [$commercialProjectEntity, $commercialQuoteEntity, $interventionEntity];
    }

    private function recorder(CommercialCalendarGateway $calendarGateway): RecordCommercialQuote
    {
        $clock = new MockClock('2026-11-12 12:00:00');
        $interventionRepository = new CommercialInterventionDoctrineRepository(self::getContainer()->get(ManagerRegistry::class));

        return new RecordCommercialQuote(new CommercialProjectWorkflow($clock), $clock, new ConfirmCommercialProjectInterventions($interventionRepository, $calendarGateway, $clock));
    }
}
