<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialProjectOverviewTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testOverviewLinksWeekSessionsBeforeRemindersAndOffersAllConfirmedProjectsSeparately(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(ClockInterface::class, new MockClock('2026-11-18 12:00:00 Europe/Paris'));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("overview-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Structure semaine {$suffix}");
        $discussionProject = new CommercialProjectEntity("Discussion {$suffix}", $organizationEntity, null, new DateTimeImmutable());
        $confirmedProject = new CommercialProjectEntity("Validé sans rappel {$suffix}", $organizationEntity, null, new DateTimeImmutable());
        $confirmedProject->setStatus(CommercialProjectEntity::STATUS_CONFIRMED);
        $reminderProject = new CommercialProjectEntity("Validé avec rappel {$suffix}", $organizationEntity, null, new DateTimeImmutable());
        $reminderProject->setStatus(CommercialProjectEntity::STATUS_CONFIRMED);
        $reminderProject->addAction(new CommercialActionEntity($reminderProject, 'Rappeler la structure', new DateTimeImmutable('2026-11-17')));
        $startsAt = new DateTimeImmutable('2026-11-16 09:00:00 UTC');
        $interventionEntity = new CommercialInterventionEntity($confirmedProject, 'test', "overview-{$suffix}", 'Séance du lundi', $startsAt, $startsAt->modify('+1 hour'), 'Europe/Paris', 'Salle de musique', '', new DateTimeImmutable());
        $interventionEntity->confirm();
        foreach ([$adminUserEntity, $organizationEntity, $discussionProject, $confirmedProject, $reminderProject, $interventionEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);
        $crawler = $client->request('GET', '/commercial/projects?query=' . $suffix);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filterXPath('//section[contains(@class, "commercial-week")]/following-sibling::div[contains(@class, "commercial-project-browser")]'));
        $weekLink = $crawler->filterXPath('//a[contains(@class, "commercial-week__row") and @href="/commercial/projects/' . $confirmedProject->getId() . '"]');
        self::assertCount(1, $weekLink);
        self::assertStringContainsString('16/11 · 10:00', $weekLink->text());
        self::assertStringContainsString('Salle de musique', $weekLink->text());
        self::assertCount(0, $crawler->filterXPath('//tr[@data-project-id="' . $confirmedProject->getId() . '"]'));
        self::assertCount(1, $crawler->filterXPath('//tr[@data-project-id="' . $reminderProject->getId() . '"]'));
        self::assertCount(1, $crawler->filterXPath('//tr[@data-project-id="' . $discussionProject->getId() . '"]'));
        self::assertCount(1, $crawler->filterXPath('//a[@href="/commercial/projects?status=confirmed"]'));

        $crawler = $client->request('GET', '/commercial/projects?status=confirmed&query=' . $suffix);
        self::assertResponseIsSuccessful();
        self::assertSame('Dossiers validés en cours', $crawler->filterXPath('//h1')->text());
        self::assertCount(0, $crawler->filterXPath('//section[contains(@class, "commercial-week")]'));
        self::assertCount(1, $crawler->filterXPath('//tr[@data-project-id="' . $confirmedProject->getId() . '"]'));
        self::assertCount(1, $crawler->filterXPath('//tr[@data-project-id="' . $reminderProject->getId() . '"]'));
        self::assertCount(0, $crawler->filterXPath('//tr[@data-project-id="' . $discussionProject->getId() . '"]'));
    }
}
