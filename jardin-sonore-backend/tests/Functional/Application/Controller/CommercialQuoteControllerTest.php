<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Commercial\CalendarEventDraft;
use App\Application\Commercial\CalendarEventOccurrence;
use App\Application\Commercial\CommercialCalendarGateway;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialEventEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialQuoteEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialQuoteControllerTest extends WebTestCase
{
    public function testSigningQuoteConfirmsOptionSessionsAndRemovesCalendarMarker(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("quote-calendar-{$suffix}@example.test")->setPassword('unused');
        $commercialProjectEntity = new CommercialProjectEntity('Ateliers', null, null, new DateTimeImmutable());
        $commercialQuoteEntity = new CommercialQuoteEntity($commercialProjectEntity, "CAL-{$suffix}", 'devis.pdf', 25000, new DateTimeImmutable('today'));
        $commercialProjectEntity->addQuote($commercialQuoteEntity);
        $start = new DateTimeImmutable('+7 days 10:00:00');
        $optionInterventionEntity = new CommercialInterventionEntity($commercialProjectEntity, 'calendar', "option-{$suffix}", 'Atelier', $start, $start->modify('+1 hour'), 'Europe/Paris', null, 'v1', new DateTimeImmutable());
        $confirmedInterventionEntity = new CommercialInterventionEntity($commercialProjectEntity, 'calendar', "confirmed-{$suffix}", 'Déjà confirmée', $start, $start->modify('+1 hour'), 'Europe/Paris', null, 'v1', new DateTimeImmutable());
        $confirmedInterventionEntity->confirm();
        $canceledInterventionEntity = new CommercialInterventionEntity($commercialProjectEntity, 'calendar', "canceled-{$suffix}", 'Annulée', $start, $start->modify('+1 hour'), 'Europe/Paris', null, 'v1', new DateTimeImmutable());
        $canceledInterventionEntity->cancel(new DateTimeImmutable());
        foreach ([$adminUserEntity, $commercialProjectEntity, $commercialQuoteEntity, $optionInterventionEntity, $confirmedInterventionEntity, $canceledInterventionEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $calendarGateway = $this->createMock(CommercialCalendarGateway::class);
        $calendarGateway->method('event')->willReturnCallback(static fn (string $id): CalendarEventOccurrence => new CalendarEventOccurrence($id, $id === $optionInterventionEntity->getGoogleEventId() ? '[OPT] Atelier' : 'Déjà confirmée', $start, $start->modify('+1 hour'), null, false, etag: 'v1'));
        $calendarGateway->expects(self::once())->method('updateEvent')->with(
            self::callback(static fn (CalendarEventOccurrence $event): bool => $event->id === $optionInterventionEntity->getGoogleEventId() && 'v1' === $event->etag),
            self::callback(static fn (CalendarEventDraft $draft): bool => 'Atelier' === $draft->title),
        )->willReturn(new CalendarEventOccurrence($optionInterventionEntity->getGoogleEventId(), 'Atelier', $start, $start->modify('+1 hour'), null, false, etag: 'v2'));
        static::getContainer()->set(CommercialCalendarGateway::class, $calendarGateway);
        $client->loginUser($adminUserEntity);
        $crawler = $client->request('GET', '/commercial/projects/' . $commercialProjectEntity->getId());
        $token = $crawler->filterXPath('//form[contains(@action, "/quotes/' . $commercialQuoteEntity->getId() . '/sign")]/input[@name="_token"]')->attr('value');
        $client->request('POST', '/commercial/quotes/' . $commercialQuoteEntity->getId() . '/sign', ['_token' => $token, 'signedOn' => (new DateTimeImmutable('today'))->format('Y-m-d')]);
        self::assertResponseRedirects();
        $currentEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $optionInterventionEntity = $currentEntityManager->find(CommercialInterventionEntity::class, $optionInterventionEntity->getId());
        self::assertSame(CommercialInterventionEntity::STATUS_CONFIRMED, $optionInterventionEntity->getStatus());
        self::assertSame('v2', $optionInterventionEntity->getEtag());
        $canceledInterventionEntity = $currentEntityManager->find(CommercialInterventionEntity::class, $canceledInterventionEntity->getId());
        self::assertSame(CommercialInterventionEntity::STATUS_CANCELED, $canceledInterventionEntity->getStatus());
    }

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testSentQuoteCanBeRecordedAndThenSigned(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("quote-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Mairie {$suffix}");
        $projectEntity = new CommercialProjectEntity('Ateliers 2027', $organizationEntity, null, new DateTimeImmutable());
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId() . '/quotes/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer le devis envoyé')->form();
        $form['commercial_quote[reference]'] = "2026-10 - d{$suffix}";
        $form['commercial_quote[filename]'] = "2026-10 - d{$suffix} - Mairie";
        $form['commercial_quote[amount]'] = '310,50';
        $form['commercial_quote[sentOn]'] = '2026-10-09';
        $client->submit($form);
        self::assertResponseRedirects();

        $quoteEntity = static::getContainer()->get(EntityManagerInterface::class)->getRepository(CommercialQuoteEntity::class)->findOneBy(['reference' => "2026-10 - d{$suffix}"]);
        self::assertInstanceOf(CommercialQuoteEntity::class, $quoteEntity);
        self::assertSame(31050, $quoteEntity->getAmountCents());
        $sentEventEntity = $quoteEntity->getProject()->getEvents()->filter(static fn ($eventEntity): bool => 'quote_sent' === $eventEntity->getType())->first();
        self::assertSame($quoteEntity, $sentEventEntity->getQuote());
        self::assertSame('2026-10-09', $sentEventEntity->getOccurredAt()->format('Y-m-d'));

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $token = $crawler->filterXPath('//form[contains(@action, "/quotes/")]/input[@name="_token"]')->attr('value');
        $client->request('POST', '/commercial/quotes/' . $quoteEntity->getId() . '/sign', ['_token' => $token, 'signedOn' => '2026-10-10']);
        self::assertResponseRedirects();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedQuoteEntity = $entityManager->find(CommercialQuoteEntity::class, $quoteEntity->getId());
        self::assertInstanceOf(CommercialQuoteEntity::class, $savedQuoteEntity);
        self::assertSame('2026-10-10', $savedQuoteEntity->getSignedOn()?->format('Y-m-d'));
        $signedEventEntity = $savedQuoteEntity->getProject()->getEvents()->filter(static fn ($eventEntity): bool => 'quote_signed' === $eventEntity->getType())->first();
        self::assertSame('2026-10-10', $signedEventEntity->getOccurredAt()->format('Y-m-d'));
        self::assertSame(CommercialProjectEntity::STATUS_CONFIRMED, $savedQuoteEntity->getProject()->getStatus());
    }

    public function testSentQuoteCanBeMarkedAsRefused(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("refusal-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Mairie {$suffix}");
        $projectEntity = new CommercialProjectEntity('Projet partenariat', $organizationEntity, null, new DateTimeImmutable());
        $quoteEntity = new CommercialQuoteEntity($projectEntity, "D5-{$suffix}", 'devis-d5.pdf', 25000, new DateTimeImmutable('2026-10-10'));
        $replacementQuoteEntity = new CommercialQuoteEntity($projectEntity, "D6-{$suffix}", 'devis-d6.pdf', 26000, new DateTimeImmutable('2026-10-14'), $quoteEntity);
        $projectEntity->addQuote($quoteEntity);
        $projectEntity->addQuote($replacementQuoteEntity);
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity, $quoteEntity, $replacementQuoteEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Envoyé', $crawler->text());
        self::assertStringContainsString('Marquer comme refusé', $crawler->text());
        $token = $crawler->filterXPath(sprintf('//form[@action="/commercial/quotes/%d/refuse"]/input[@name="_token"]', $quoteEntity->getId()))->attr('value');

        $client->request('POST', '/commercial/quotes/' . $quoteEntity->getId() . '/refuse', ['_token' => $token, 'refusedOn' => '2026-10-14']);

        self::assertResponseRedirects('/commercial/projects/' . $projectEntity->getId());
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedQuoteEntity = $entityManager->find(CommercialQuoteEntity::class, $quoteEntity->getId());
        self::assertInstanceOf(CommercialQuoteEntity::class, $savedQuoteEntity);
        self::assertSame('2026-10-14', $savedQuoteEntity->getRefusedOn()?->format('Y-m-d'));
        $refusedEventEntity = $savedQuoteEntity->getProject()->getEvents()->filter(static fn ($eventEntity): bool => 'quote_refused' === $eventEntity->getType())->first();
        self::assertSame('2026-10-14', $refusedEventEntity->getOccurredAt()->format('Y-m-d'));
    }

    public function testSignedQuoteCanBeDeletedWithItsTimelineEvents(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("quote-delete-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Mairie {$suffix}");
        $projectEntity = new CommercialProjectEntity('Projet avec devis erroné', $organizationEntity, null, new DateTimeImmutable());
        $quoteEntity = new CommercialQuoteEntity($projectEntity, "D-{$suffix}", 'devis-errone.pdf', 25000, new DateTimeImmutable('2026-10-10'));
        $quoteEntity->markSigned(new DateTimeImmutable('2026-10-11'));
        $replacementQuoteEntity = new CommercialQuoteEntity($projectEntity, "D-remplacement-{$suffix}", 'devis-corrige.pdf', 26000, new DateTimeImmutable('2026-10-12'), $quoteEntity);
        $projectEntity->addQuote($quoteEntity);
        $projectEntity->addQuote($replacementQuoteEntity);
        $sentEventEntity = $projectEntity->recordEvent('quote_sent', new DateTimeImmutable('2026-10-10'), $quoteEntity->getReference());
        $signedEventEntity = $projectEntity->recordEvent('quote_signed', new DateTimeImmutable('2026-10-16 15:58:00'), $quoteEntity->getReference());
        $signedEventEntity->linkQuote($quoteEntity);
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity, $quoteEntity, $replacementQuoteEntity, $sentEventEntity, $signedEventEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        self::assertResponseIsSuccessful();
        self::assertSame('11/10/2026', trim($crawler->filterXPath('//article[contains(@class, "commercial-timeline__item--quote-signed")]//time')->text()));
        $token = $crawler->filterXPath(sprintf('//button[contains(@class, "commercial-dossier__quote-delete") and @data-confirmation-dialog-action-value="/commercial/quotes/%d/delete"]', $quoteEntity->getId()))->attr('data-confirmation-dialog-token-value');

        $client->request('POST', '/commercial/quotes/' . $quoteEntity->getId() . '/delete', ['_token' => $token]);

        self::assertResponseRedirects('/commercial/projects/' . $projectEntity->getId());
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertNull($entityManager->find(CommercialQuoteEntity::class, $quoteEntity->getId()));
        self::assertNull($entityManager->find(CommercialEventEntity::class, $sentEventEntity->getId()));
        self::assertNull($entityManager->find(CommercialEventEntity::class, $signedEventEntity->getId()));
        $savedReplacementQuoteEntity = $entityManager->find(CommercialQuoteEntity::class, $replacementQuoteEntity->getId());
        self::assertInstanceOf(CommercialQuoteEntity::class, $savedReplacementQuoteEntity);
        self::assertNull($savedReplacementQuoteEntity->getReplaces());
    }
}
