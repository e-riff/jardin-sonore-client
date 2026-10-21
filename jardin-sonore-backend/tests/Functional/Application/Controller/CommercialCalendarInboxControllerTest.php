<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialCalendarGateway;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\GoogleCalendarConnectionEntity;
use App\Kernel;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialCalendarInboxControllerTest extends WebTestCase
{
    private ?Connection $connection = null;

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    protected function tearDown(): void
    {
        while ($this->connection?->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testFreeAndLinkedAppointmentsCannotBeAttachedAsSessions(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $entityManager->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
        $now = new DateTimeImmutable('2026-10-20 10:00:00', new DateTimeZone('Europe/Paris'));
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("inbox-rdv-{$suffix}@example.test")->setPassword('unused');
        $projectEntity = new CommercialProjectEntity('Dossier', null, null, $now);
        $calendarId = (string) $container->getParameter('app.commercial.calendar_id');
        $linkedId = "linked-rdv-{$suffix}";
        $freeId = "free-rdv-{$suffix}";
        $normalId = "session-{$suffix}";
        $start = new DateTimeImmutable('2026-10-22 14:00:00', new DateTimeZone('Europe/Paris'));
        $appointmentEntity = new CommercialAppointmentEntity($projectEntity, $calendarId, str_repeat('e', 32), new CalendarEventOccurrence($linkedId, 'RDV · Crèche', $start, $start->modify('+1 hour'), null, false), $now);
        $connectionEntity = $entityManager->find(GoogleCalendarConnectionEntity::class, GoogleCalendarConnectionEntity::SINGLETON_ID);
        if (!$connectionEntity instanceof GoogleCalendarConnectionEntity) {
            $entityManager->persist(new GoogleCalendarConnectionEntity('test-token', 'calendar@example.test', $now));
        }
        foreach ([$adminUserEntity, $projectEntity, $appointmentEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);
        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->method('eventsBetween')->willReturn([
            new CalendarEventOccurrence($freeId, 'RDV—Direction', $start, $start->modify('+1 hour'), null, false),
            new CalendarEventOccurrence($linkedId, 'Échange renommé', $start, $start->modify('+1 hour'), null, false),
            new CalendarEventOccurrence($normalId, 'Séance libre', $start, $start->modify('+1 hour'), null, false),
        ]);
        $calendarGateway->expects(self::never())->method('updateEvent');
        $container->set(CommercialCalendarGateway::class, $calendarGateway);

        $crawler = $client->request('GET', '/commercial/calendar/unlinked?from=2026-10-01&to=2026-10-31');
        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Séance libre', $content);
        self::assertStringNotContainsString('RDV—Direction', $content);
        self::assertStringNotContainsString('Échange renommé', $content);
        $token = $crawler->filterXPath('//form[@action="/commercial/calendar/unlinked/attach"]//input[@name="_token"]')->attr('value');

        foreach ([$freeId, $linkedId] as $eventId) {
            $client->request('POST', '/commercial/calendar/unlinked/attach', [
                '_token' => $token,
                'event_ids' => [$eventId],
                'project_id' => (string) $projectEntity->getId(),
                'status' => 'confirmed',
                'from' => '2026-10-01',
                'to' => '2026-10-31',
            ]);
            self::assertResponseRedirects();
        }
        self::assertSame([], $entityManager->getRepository(CommercialInterventionEntity::class)->findBy(['project' => $projectEntity]));
    }
}
