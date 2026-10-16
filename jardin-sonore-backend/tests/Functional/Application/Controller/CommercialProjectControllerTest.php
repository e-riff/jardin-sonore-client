<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialEventEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectPersonEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactLinkEntity;
use App\Kernel;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialProjectControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testDossierShowsContactsTasksDocumentsAndNewestTimelineEntry(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-layout-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $organizationEntity->getContactDetails()?->addPhoneContactLink((new PhoneContactLinkEntity())->setPhoneNumber('0612345678'));
        $organizationEntity->getContactDetails()?->addEmailContactLink((new EmailContactLinkEntity())->setEmailAddress("direction-{$suffix}@example.test"));
        $personEntity = (new PersonEntity())->setFirstName('Camille')->setLastName('Martin');
        $personEntity->getContactDetails()?->addPhoneContactLink((new PhoneContactLinkEntity())->setPhoneNumber('0678901234'));
        $organizationEntity->addPerson($personEntity);
        $projectEntity = new CommercialProjectEntity('Ateliers du printemps', $organizationEntity, null, new DateTimeImmutable('2026-10-10'));
        $projectEntity->addPerson(new CommercialProjectPersonEntity($projectEntity, $personEntity, new DateTimeImmutable('2026-10-10')));
        $projectEntity->setPrimaryContact($personEntity);
        $projectEntity->addAction(new CommercialActionEntity($projectEntity, 'Envoyer le programme', new DateTimeImmutable('2026-10-15')));
        $projectEntity->recordEvent('initial_message', new DateTimeImmutable('2026-10-10 09:00:00'), 'Premier message');
        $projectEntity->recordEvent('quote_sent', new DateTimeImmutable('2026-10-11 09:00:00'), 'DEV-2026-001');
        $projectEntity->recordEvent('quote_signed', new DateTimeImmutable('2026-10-12 09:00:00'), 'DEV-2026-001');
        $projectEntity->recordEvent('quote_refused', new DateTimeImmutable('2026-10-12 10:00:00'), 'DEV-2026-002');
        $projectEntity->recordEvent('note', new DateTimeImmutable('2026-10-13 10:00:00'), str_repeat('Compte rendu détaillé. ', 18));
        $projectEntity->recordEvent('quote_sent', new DateTimeImmutable('2026-10-14 10:00:00'), 'DEV-2026-003');
        $novemberInterventionEntity = new CommercialInterventionEntity($projectEntity, 'calendar', "event-november-{$suffix}", 'Atelier de novembre', new DateTimeImmutable('2026-11-03 09:00:00'), new DateTimeImmutable('2026-11-03 11:00:00'), 'Europe/Paris', null, '', new DateTimeImmutable('2026-10-13'));
        $novemberInterventionEntity->confirm();
        $decemberInterventionEntity = new CommercialInterventionEntity($projectEntity, 'calendar', "event-december-{$suffix}", 'Atelier de décembre', new DateTimeImmutable('2026-12-14 14:00:00'), new DateTimeImmutable('2026-12-14 16:00:00'), 'Europe/Paris', null, '', new DateTimeImmutable('2026-10-13'));
        $decemberInterventionEntity->confirm();
        foreach ([$adminUserEntity, $organizationEntity, $personEntity, $projectEntity, $novemberInterventionEntity, $decemberInterventionEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filterXPath('//details[contains(@class, "commercial-dossier__contacts")]//span[contains(@class, "commercial-contact-value") and contains(text(), "+33612345678")]/following-sibling::a[@href="tel:+33612345678"]'));
        self::assertCount(1, $crawler->filterXPath('//details[contains(@class, "commercial-dossier__contacts")]//span[contains(@class, "commercial-contact-value") and contains(text(), "+33678901234")]/following-sibling::a[@href="tel:+33678901234"]'));
        self::assertCount(1, $crawler->filterXPath('//a[@href="mailto:direction-' . $suffix . '@example.test"]'));
        self::assertCount(1, $crawler->filterXPath('//details[contains(@class, "commercial-dossier__contact-tools") and not(@open)]'));
        self::assertCount(1, $crawler->filterXPath('//details[contains(@class, "commercial-dossier__documents") and not(@open)]'));
        self::assertCount(1, $crawler->filterXPath('//details[contains(@class, "commercial-dossier__contacts") and @data-controller="responsive-details" and not(@open)]'));
        self::assertCount(1, $crawler->filterXPath('//*[contains(@class, "commercial-dossier__task")]//time[@datetime="2026-10-15"]'));
        self::assertCount(4, $crawler->filterXPath('//*[contains(@class, "commercial-dossier__task")]//form[contains(@action, "/postpone")]'));
        $timelineItems = $crawler->filterXPath('//*[contains(@class, "commercial-timeline__item")]');
        self::assertCount(6, $timelineItems);
        self::assertCount(1, $timelineItems->first()->filterXPath('.//*[contains(@class, "commercial-timeline__quote-reference")]'));
        self::assertNull($timelineItems->first()->attr('data-controller'));
        self::assertStringContainsString('Compte rendu détaillé', $timelineItems->eq(1)->text());
        self::assertSame('true', $timelineItems->eq(1)->attr('data-commercial-timeline-expanded-value'));
        self::assertCount(2, $crawler->filterXPath('//*[contains(@class, "commercial-timeline__item--quote-sent")]//*[contains(@class, "commercial-timeline__event-icon--send")]'));
        self::assertCount(1, $crawler->filterXPath('//*[contains(@class, "commercial-timeline__item--quote-signed")]//*[contains(@class, "commercial-timeline__event-icon--signed")]'));
        self::assertCount(1, $crawler->filterXPath('//*[contains(@class, "commercial-timeline__item--quote-refused")]//*[contains(@class, "commercial-timeline__event-icon--close")]'));
        self::assertCount(2, $crawler->filterXPath('//*[contains(@class, "commercial-timeline__item")]//*[contains(@class, "commercial-timeline__edit")]'));
        $interventionMonths = $crawler->filterXPath('//*[contains(concat(" ", normalize-space(@class), " "), " commercial-dossier__intervention-month ")]');
        self::assertCount(2, $interventionMonths);
        self::assertStringContainsString('novembre 2026', $interventionMonths->first()->text());
        self::assertStringContainsString('3 novembre 2026', $crawler->filterXPath('//*[contains(concat(" ", normalize-space(@class), " "), " commercial-dossier__intervention-date ")]')->first()->text());
    }

    public function testManualCreationOpensDossierWithoutCreatingARequest(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-new-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($organizationEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/new');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Nouveau dossier', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('commercial_project_create[emailAddress]', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('commercial_project_create[phone]', (string) $client->getResponse()->getContent());
        $form = $crawler->selectButton('Créer le dossier')->form();
        $requestCount = $entityManager->getRepository(CommercialRequestEntity::class)->count([]);
        $client->request('POST', '/commercial/projects/new', [
            'commercial_project_create' => [
                '_token' => $form['commercial_project_create[_token]']->getValue(),
                'organization' => (string) $organizationEntity->getId(),
                'firstMessage' => 'Appel pour des ateliers au printemps',
                'initialStatus' => 'confirmed',
            ],
        ]);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedProjectEntity = $entityManager->getRepository(CommercialProjectEntity::class)->findOneBy(['organization' => $organizationEntity->getId()], ['id' => 'DESC']);
        self::assertInstanceOf(CommercialProjectEntity::class, $savedProjectEntity);
        self::assertSame('confirmed', $savedProjectEntity->getStatus());
        self::assertStringContainsString($organizationEntity->getName(), $savedProjectEntity->getTitle());
        self::assertCount(0, $savedProjectEntity->getActions());
        self::assertCount(1, $savedProjectEntity->getEvents());
        self::assertSame('initial_message', $savedProjectEntity->getEvents()->first()->getType());
        self::assertSame('Appel pour des ateliers au printemps', $savedProjectEntity->getEvents()->first()->getContent());
        self::assertSame($requestCount, $entityManager->getRepository(CommercialRequestEntity::class)->count([]));
    }

    public function testManualCreationCanCreateOrganizationAndPersonWithSuggestedTask(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-create-{$suffix}@example.test")->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/new');
        $form = $crawler->selectButton('Créer le dossier')->form();
        $client->request('POST', '/commercial/projects/new', [
            'commercial_project_create' => [
                '_token' => $form['commercial_project_create[_token]']->getValue(),
                'newOrganizationName' => "Nouvelle crèche {$suffix}",
                'newPersonFirstName' => 'Camille',
                'newPersonLastName' => 'Martin',
                'newPersonRole' => 'Direction',
                'firstMessage' => 'Premier mail reçu',
                'initialStatus' => 'discussion',
                'addTask' => '1',
                'taskTitle' => 'Répondre',
                'taskDueOn' => '2026-10-16',
            ],
        ]);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $organizationEntity = $entityManager->getRepository(OrganizationEntity::class)->findOneBy(['name' => "Nouvelle crèche {$suffix}"]);
        self::assertInstanceOf(OrganizationEntity::class, $organizationEntity);
        $projectEntity = $entityManager->getRepository(CommercialProjectEntity::class)->findOneBy(['organization' => $organizationEntity->getId()]);
        self::assertInstanceOf(CommercialProjectEntity::class, $projectEntity);
        self::assertSame('Camille Martin', (string) $projectEntity->getPrimaryContact());
        self::assertSame($organizationEntity->getId(), $projectEntity->getPrimaryContact()?->getOrganization()?->getId());
        self::assertCount(1, $projectEntity->getActions());
        self::assertSame('Répondre', $projectEntity->getActions()->first()->getTitle());
        self::assertSame('2026-10-16', $projectEntity->getActions()->first()->getDueOn()->format('Y-m-d'));
    }

    public function testManualCreationCanSelectAnExistingPersonAndTodayAsTaskDate(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-person-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $personEntity = (new PersonEntity())->setFirstName('Camille')->setLastName('Martin');
        $organizationEntity->addPerson($personEntity);
        foreach ([$adminUserEntity, $organizationEntity, $personEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/new');
        $form = $crawler->selectButton('Créer le dossier')->form();
        $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d');
        self::assertSame($today, $form['commercial_project_create[taskDueOn]']->getValue());
        $client->request('POST', '/commercial/projects/new', [
            'commercial_project_create' => [
                '_token' => $form['commercial_project_create[_token]']->getValue(),
                'organization' => (string) $organizationEntity->getId(),
                'person' => (string) $personEntity->getId(),
                'firstMessage' => 'Message reçu',
                'initialStatus' => 'discussion',
                'addTask' => '1',
                'taskTitle' => 'Répondre',
                'taskDueOn' => $today,
            ],
        ]);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $projectEntity = $entityManager->getRepository(CommercialProjectEntity::class)->findOneBy(['organization' => $organizationEntity->getId()], ['id' => 'DESC']);
        self::assertSame($personEntity->getId(), $projectEntity->getPrimaryContact()?->getId());
        self::assertSame($today, $projectEntity->getActions()->first()->getDueOn()->format('Y-m-d'));
    }

    public function testManualCreationRejectsPersonFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-invalid-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $otherOrganizationEntity = (new OrganizationEntity())->setName("Mairie {$suffix}");
        $otherPersonEntity = (new PersonEntity())->setFirstName('Autre')->setLastName('Contact');
        $otherOrganizationEntity->addPerson($otherPersonEntity);
        foreach ([$adminUserEntity, $organizationEntity, $otherOrganizationEntity, $otherPersonEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/new');
        $form = $crawler->selectButton('Créer le dossier')->form();
        $client->request('POST', '/commercial/projects/new', [
            'commercial_project_create' => [
                '_token' => $form['commercial_project_create[_token]']->getValue(),
                'organization' => (string) $organizationEntity->getId(),
                'person' => (string) $otherPersonEntity->getId(),
                'firstMessage' => 'Premier échange',
                'initialStatus' => 'discussion',
            ],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $entityManager->getRepository(CommercialProjectEntity::class)->count(['organization' => $organizationEntity]));
    }

    public function testProvisionalDossierCanBeAttachedWithoutChangingOriginalRequest(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-attach-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $requestEntity = new CommercialRequestEntity('site', 'Camille', 'camille@example.test', 'Texte original', new DateTimeImmutable('2026-10-13 09:00:00'), 'Nom libre', null, '+33612345678');
        $projectEntity = new CommercialProjectEntity('Nom libre', null, $requestEntity, new DateTimeImmutable('2026-10-13 09:00:00'));
        $requestEntity->addProject($projectEntity);
        foreach ([$adminUserEntity, $organizationEntity, $requestEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filterXPath('//span[contains(@class, "commercial-contact-value") and contains(text(), "+33612345678")]/following-sibling::a[@href="tel:+33612345678"]'));
        self::assertCount(1, $crawler->filterXPath('//a[@href="mailto:camille@example.test"]'));
        $form = $crawler->selectButton('Rattacher la structure')->form();
        $client->request('POST', '/commercial/projects/' . $projectEntity->getId() . '/organization', [
            'commercial_project_organization' => [
                '_token' => $form['commercial_project_organization[_token]']->getValue(),
                'organization' => (string) $organizationEntity->getId(),
            ],
        ]);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedProjectEntity = $entityManager->find(CommercialProjectEntity::class, $projectEntity->getId());
        self::assertSame($organizationEntity->getId(), $savedProjectEntity->getOrganization()?->getId());
        self::assertSame('Nom libre', $savedProjectEntity->getRequest()?->getOrganizationName());
        self::assertSame('Texte original', $savedProjectEntity->getRequest()?->getMessage());
        self::assertSame('camille@example.test', $savedProjectEntity->getRequest()?->getEmailAddress());
    }

    public function testProvisionalDossierCanCreateItsOrganization(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-new-attach-{$suffix}@example.test")->setPassword('unused');
        $requestEntity = new CommercialRequestEntity('site', 'Camille', 'camille@example.test', 'Message', new DateTimeImmutable('2026-10-13 09:00:00'), 'Nom brut');
        $projectEntity = new CommercialProjectEntity('Nom brut', null, $requestEntity, new DateTimeImmutable('2026-10-13 09:00:00'));
        $requestEntity->addProject($projectEntity);
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($requestEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $form = $crawler->selectButton('Rattacher la structure')->form();
        $client->request('POST', '/commercial/projects/' . $projectEntity->getId() . '/organization', [
            'commercial_project_organization' => [
                '_token' => $form['commercial_project_organization[_token]']->getValue(),
                'newOrganizationName' => "Crèche créée {$suffix}",
            ],
        ]);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedProjectEntity = $entityManager->find(CommercialProjectEntity::class, $projectEntity->getId());
        self::assertSame("Crèche créée {$suffix}", $savedProjectEntity->getOrganization()?->getName());
        self::assertSame('Nom brut', $savedProjectEntity->getRequest()?->getOrganizationName());
    }

    public function testNoteCanCreateOptionalNextActionWithoutClosingProject(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $projectEntity = new CommercialProjectEntity('Ateliers 2027', $organizationEntity, null, new DateTimeImmutable());
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer la note')->form();
        $form['commercial_note[content]'] = 'Appel avec la direction';
        $form['commercial_note[nextActionTitle]'] = 'Envoyer les dates';
        $form['commercial_note[nextActionDueOn]'] = '2026-10-16';
        $client->submit($form);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedProjectEntity = $entityManager->find(CommercialProjectEntity::class, $projectEntity->getId());
        self::assertInstanceOf(CommercialProjectEntity::class, $savedProjectEntity);
        self::assertSame(CommercialProjectEntity::STATUS_DISCUSSION, $savedProjectEntity->getStatus());
        self::assertCount(1, $savedProjectEntity->getActions());
        self::assertSame('Envoyer les dates', $savedProjectEntity->getActions()->first()->getTitle());
        self::assertSame('Appel avec la direction', $savedProjectEntity->getEvents()->filter(static fn ($eventEntity): bool => 'note' === $eventEntity->getType())->first()->getContent());
    }

    public function testTaskAndTimelineEntryCanBeCreatedSeparately(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-quick-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $personEntity = (new PersonEntity())->setFirstName('Camille')->setLastName('Martin');
        $projectEntity = new CommercialProjectEntity('Ateliers', $organizationEntity, null, new DateTimeImmutable());
        $organizationEntity->addPerson($personEntity);
        $projectEntity->addPerson(new CommercialProjectPersonEntity($projectEntity, $personEntity, new DateTimeImmutable()));
        foreach ([$adminUserEntity, $organizationEntity, $personEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $taskForm = $crawler->filterXPath('//form[@action="/commercial/projects/' . $projectEntity->getId() . '/actions"]')->form();
        $taskForm['title'] = 'Envoyer le devis';
        $taskForm['dueOn'] = '2026-10-16';
        $client->submit($taskForm);
        self::assertResponseRedirects();
        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $eventForm = $crawler->selectButton('Enregistrer la note')->form();
        $eventForm['commercial_note[content]'] = 'Appel avec la direction';
        $eventForm['commercial_note[nextActionTitle]'] = '';
        $client->submit($eventForm);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedProjectEntity = $entityManager->find(CommercialProjectEntity::class, $projectEntity->getId());
        self::assertCount(1, $savedProjectEntity->getActions());
        self::assertSame('Envoyer le devis', $savedProjectEntity->getActions()->first()->getTitle());
        self::assertCount(1, $savedProjectEntity->getEvents()->filter(static fn ($eventEntity): bool => 'note' === $eventEntity->getType()));
        self::assertSame('Appel avec la direction', $savedProjectEntity->getEvents()->last()->getContent());
    }

    public function testTimelineCorrectionChangesItsDateAndRejectsAutomaticEvents(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-event-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $projectEntity = new CommercialProjectEntity('Ateliers', $organizationEntity, null, new DateTimeImmutable());
        $occurredAt = new DateTimeImmutable('2026-10-09 10:00:00');
        $noteEntity = $projectEntity->recordEvent('note', $occurredAt, 'Ancienne saisie');
        $automaticEventEntity = $projectEntity->recordEvent('project_confirmed', $occurredAt);
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $token = $crawler->filterXPath('//form[contains(@action, "/events/' . $noteEntity->getId() . '/edit")]/input[@name="_token"]')->attr('value');
        self::assertSame('2026-10-09', $crawler->filterXPath('//form[contains(@action, "/events/' . $noteEntity->getId() . '/edit")]//input[@name="occurredOn"]')->attr('value'));
        self::assertCount(0, $crawler->filterXPath('//form[contains(@action, "/events/' . $automaticEventEntity->getId() . '/edit")]'));
        $client->request('POST', '/commercial/projects/' . $projectEntity->getId() . '/events/' . $noteEntity->getId() . '/edit', ['_token' => $token, 'content' => 'Saisie corrigée', 'occurredOn' => '2026-09-30']);
        self::assertResponseRedirects();
        $client->request('POST', '/commercial/projects/' . $projectEntity->getId() . '/events/' . $automaticEventEntity->getId() . '/edit', ['_token' => 'invalid', 'content' => 'Autre']);
        self::assertResponseStatusCodeSame(403);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedNoteEntity = $entityManager->find(CommercialEventEntity::class, $noteEntity->getId());
        self::assertSame('Saisie corrigée', $savedNoteEntity->getContent());
        self::assertSame('2026-09-30 10:00:00', $savedNoteEntity->getOccurredAt()->format('Y-m-d H:i:s'));
    }

    public function testManualStatusCanBeChangedAndReopened(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-status-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $projectEntity = new CommercialProjectEntity('Ateliers', $organizationEntity, null, new DateTimeImmutable());
        $actionEntity = new CommercialActionEntity($projectEntity, 'Relancer', new DateTimeImmutable('2026-10-16'));
        $projectEntity->addAction($actionEntity);
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $token = $crawler->filterXPath('//form[contains(@action, "/status") and not(contains(@action, "/status/"))]/input[@name="_token"]')->attr('value');
        $client->request('POST', '/commercial/projects/' . $projectEntity->getId() . '/status', ['_token' => $token, 'status' => 'without_result']);
        self::assertResponseRedirects();
        $client->request('POST', '/commercial/projects/' . $projectEntity->getId() . '/status', ['_token' => $token, 'status' => 'discussion']);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedProjectEntity = $entityManager->find(CommercialProjectEntity::class, $projectEntity->getId());
        self::assertSame(CommercialProjectEntity::STATUS_DISCUSSION, $savedProjectEntity->getStatus());
        self::assertSame(CommercialActionEntity::STATUS_CANCELED, $savedProjectEntity->getActions()->first()->getStatus());
    }

    public function testInvalidNoteOnProvisionalDossierReturnsValidationResponse(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("project-provisional-note-{$suffix}@example.test")->setPassword('unused');
        $requestEntity = new CommercialRequestEntity('site', 'Camille', 'camille@example.test', 'Demande initiale', new DateTimeImmutable());
        $projectEntity = new CommercialProjectEntity('Dossier provisoire', null, $requestEntity, new DateTimeImmutable());
        $requestEntity->addProject($projectEntity);
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($requestEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $form = $crawler->selectButton('Enregistrer la note')->form();
        $form['commercial_note[content]'] = 'Échange noté';
        $form['commercial_note[nextActionTitle]'] = 'Relancer';
        $form['commercial_note[nextActionDueOn]'] = '';
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
    }
}
