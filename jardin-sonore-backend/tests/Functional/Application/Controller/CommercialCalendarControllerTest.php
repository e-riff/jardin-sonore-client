<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialCalendarGateway;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\GoogleCalendarConnectionEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialCalendarControllerTest extends WebTestCase
{
    private ?Connection $connection = null;

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        while ($this->connection?->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testPlannerSummarizesSchoolYearAndAllowsChangingSummaryPeriod(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $entityManager->getConnection();
        $this->connection->beginTransaction();
        $timeZone = new DateTimeZone('Europe/Paris');
        $now = new DateTimeImmutable('2026-10-16 10:00:00', $timeZone);
        $container->set(ClockInterface::class, new MockClock($now));

        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("calendar-summary-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche calendrier {$suffix}");
        $projectEntity = new CommercialProjectEntity('Calendrier', $organizationEntity, null, $now);
        $calendarId = (string) $container->getParameter('app.commercial.calendar_id');
        $optionEventId = "option-{$suffix}";
        $confirmedEventId = "confirmed-{$suffix}";
        $canceledEventId = "canceled-{$suffix}";
        $optionInterventionEntity = new CommercialInterventionEntity($projectEntity, $calendarId, $optionEventId, 'Option', new DateTimeImmutable('2026-10-20 09:00:00', $timeZone), new DateTimeImmutable('2026-10-20 10:00:00', $timeZone), 'Europe/Paris', null, '', $now);
        $confirmedInterventionEntity = new CommercialInterventionEntity($projectEntity, $calendarId, $confirmedEventId, 'Confirmée', new DateTimeImmutable('2026-11-10 09:00:00', $timeZone), new DateTimeImmutable('2026-11-10 10:00:00', $timeZone), 'Europe/Paris', null, '', $now);
        $confirmedInterventionEntity->confirm();
        $canceledInterventionEntity = new CommercialInterventionEntity($projectEntity, $calendarId, $canceledEventId, 'Annulée', new DateTimeImmutable('2026-11-12 09:00:00', $timeZone), new DateTimeImmutable('2026-11-12 10:00:00', $timeZone), 'Europe/Paris', null, '', $now);
        $canceledInterventionEntity->cancel($now);
        $connectionEntity = $entityManager->find(GoogleCalendarConnectionEntity::class, GoogleCalendarConnectionEntity::SINGLETON_ID);
        if (!$connectionEntity instanceof GoogleCalendarConnectionEntity) {
            $connectionEntity = new GoogleCalendarConnectionEntity('test-token', 'calendar@example.test', $now);
            $entityManager->persist($connectionEntity);
        }

        foreach ([$adminUserEntity, $organizationEntity, $projectEntity, $optionInterventionEntity, $confirmedInterventionEntity, $canceledInterventionEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->expects(self::once())
            ->method('eventsBetween')
            ->with(
                self::callback(static fn (DateTimeImmutable $date): bool => '2026-09-01' === $date->format('Y-m-d')),
                self::callback(static fn (DateTimeImmutable $date): bool => '2027-09-01' === $date->format('Y-m-d')),
            )
            ->willReturn([
                new CalendarEventOccurrence($optionEventId, 'Option', new DateTimeImmutable('2026-10-20 09:00:00', $timeZone), new DateTimeImmutable('2026-10-20 10:00:00', $timeZone), null, false),
                new CalendarEventOccurrence($confirmedEventId, 'Confirmée', new DateTimeImmutable('2026-11-10 09:00:00', $timeZone), new DateTimeImmutable('2026-11-10 10:00:00', $timeZone), null, false),
                new CalendarEventOccurrence("unlinked-{$suffix}", 'À rattacher', new DateTimeImmutable('2026-12-01 09:00:00', $timeZone), new DateTimeImmutable('2026-12-01 10:00:00', $timeZone), null, false),
                new CalendarEventOccurrence($canceledEventId, 'Annulée', new DateTimeImmutable('2026-11-12 09:00:00', $timeZone), new DateTimeImmutable('2026-11-12 10:00:00', $timeZone), null, false),
                new CalendarEventOccurrence("all-day-{$suffix}", 'Indisponible', new DateTimeImmutable('2026-11-13 00:00:00', $timeZone), new DateTimeImmutable('2026-11-14 00:00:00', $timeZone), null, true),
            ]);
        $container->set(CommercialCalendarGateway::class, $calendarGateway);

        $crawler = $client->request('GET', '/commercial/calendar');

        self::assertResponseIsSuccessful();
        self::assertSame('2026-09-01', $crawler->filterXPath('//details[contains(@class, "commercial-calendar-summary__edit")]//input[@name="summaryFrom"]')->attr('value'));
        self::assertSame('2027-09-01', $crawler->filterXPath('//details[contains(@class, "commercial-calendar-summary__edit")]//input[@name="summaryTo"]')->attr('value'));
        self::assertSame('3', trim($crawler->filterXPath('//*[contains(@class, "commercial-calendar-summary__total")]')->text()));
        $summaryDetails = $crawler->filterXPath('//*[contains(@class, "commercial-calendar-summary__details")]')->text();
        self::assertStringContainsString('1 confirmées', $summaryDetails);
        self::assertStringContainsString('1 en option', $summaryDetails);
        self::assertStringContainsString('1 à rattacher', $summaryDetails);
    }
}
