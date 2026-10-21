<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Commercial\CalendarEventDraft;
use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialCalendarGateway;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialAppointmentControllerTest extends WebTestCase
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

    public function testCreatesAndSummarizesAnAppointmentWithoutInvitingContacts(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $entityManager->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
        $client->disableReboot();

        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("appointment-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $projectEntity = new CommercialProjectEntity('Dossier RDV', $organizationEntity, null, new DateTimeImmutable());
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $start = new DateTimeImmutable('2026-10-22 14:00:00', new DateTimeZone('Europe/Paris'));
        $end = $start->modify('+1 hour');
        $requestId = str_repeat('d', 32);
        $googleRequestId = substr(hash('sha256', $projectEntity->getId() . ':' . $requestId), 0, 32);
        $event = new CalendarEventOccurrence($googleRequestId, 'RDV · Appeler la crèche', $start, $end, 'À distance', false, etag: 'v1');
        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->expects(self::once())->method('createEvent')->with(self::callback(static fn (CalendarEventDraft $draft): bool => 'RDV · Appeler la crèche' === $draft->title && 'À distance' === $draft->location), $googleRequestId)->willReturn($event);
        $currentGoogleEvent = $event;
        $calendarGateway->method('event')->willReturnCallback(static function () use (&$currentGoogleEvent): ?CalendarEventOccurrence { return $currentGoogleEvent; });
        $calendarGateway->expects(self::once())->method('updateEvent')->willReturnCallback(static function (CalendarEventOccurrence $current, CalendarEventDraft $draft) use (&$currentGoogleEvent): CalendarEventOccurrence {
            $currentGoogleEvent = new CalendarEventOccurrence($current->id, $draft->title, $draft->startsAt, $draft->endsAt, $draft->location, false, etag: 'v2');

            return $currentGoogleEvent;
        });
        $calendarGateway->expects(self::once())->method('deleteEvent')->willReturnCallback(static function () use (&$currentGoogleEvent): void { $currentGoogleEvent = null; });
        $container->set(CommercialCalendarGateway::class, $calendarGateway);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        self::assertResponseIsSuccessful();
        $createForm = $crawler->filterXPath('//form[@action="/commercial/projects/' . $projectEntity->getId() . '/appointments"]')->form();
        $createForm['title'] = 'Appeler la crèche';
        $createForm['startsAt'] = '2026-10-22T14:00';
        $createForm['endsAt'] = '2026-10-22T15:00';
        $createForm['location'] = 'À distance';
        $createForm['requestId'] = $requestId;
        $client->submit($createForm);
        self::assertResponseRedirects('/commercial/projects/' . $projectEntity->getId());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $appointmentEntity = $entityManager->getRepository(CommercialAppointmentEntity::class)->findOneBy(['requestId' => $requestId]);
        self::assertInstanceOf(CommercialAppointmentEntity::class, $appointmentEntity);
        self::assertSame($projectEntity->getId(), $appointmentEntity->getProject()->getId());
        self::assertSame('scheduled', $appointmentEntity->getStatus());

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('RDV · Appeler la crèche', (string) $client->getResponse()->getContent());
        $summaryForm = $crawler->filterXPath('//form[@action="/commercial/appointments/' . $appointmentEntity->getId() . '/summary"]')->form();
        $summaryForm['summary'] = 'Réponse positive';
        $client->submit($summaryForm);
        self::assertResponseRedirects();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertSame('Réponse positive', $entityManager->find(CommercialAppointmentEntity::class, $appointmentEntity->getId())->getSummary());

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $editForm = $crawler->filterXPath('//form[@action="/commercial/appointments/' . $appointmentEntity->getId() . '/edit"]')->form();
        $editForm['title'] = 'Échange déplacé';
        $editForm['startsAt'] = '2026-10-23T15:00';
        $editForm['endsAt'] = '2026-10-23T16:00';
        $client->submit($editForm);
        self::assertResponseRedirects();
        $entityManager->clear();
        $savedAppointmentEntity = $entityManager->find(CommercialAppointmentEntity::class, $appointmentEntity->getId());
        self::assertSame('RDV · Échange déplacé', $savedAppointmentEntity->getTitle());
        self::assertSame('Réponse positive', $savedAppointmentEntity->getSummary());

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $cancelForm = $crawler->filterXPath('//form[@action="/commercial/appointments/' . $appointmentEntity->getId() . '/cancel"]')->form();
        $client->submit($cancelForm);
        self::assertResponseRedirects();
        $entityManager->clear();
        $savedAppointmentEntity = $entityManager->find(CommercialAppointmentEntity::class, $appointmentEntity->getId());
        self::assertSame(CommercialAppointmentEntity::STATUS_CANCELED, $savedAppointmentEntity->getStatus());
        self::assertSame('Réponse positive', $savedAppointmentEntity->getSummary());
        $eventTypes = array_map(static fn ($eventEntity): string => $eventEntity->getType(), $entityManager->find(CommercialProjectEntity::class, $projectEntity->getId())->getEvents()->toArray());
        self::assertContains('appointment_created', $eventTypes);
        self::assertContains('appointment_updated', $eventTypes);
        self::assertContains('appointment_canceled', $eventTypes);
    }
}
