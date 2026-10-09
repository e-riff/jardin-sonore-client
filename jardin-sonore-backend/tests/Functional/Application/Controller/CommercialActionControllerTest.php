<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialActionControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testActionPostponeAndCompletionKeepHistory(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("action-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $projectEntity = new CommercialProjectEntity('Ateliers 2027', $organizationEntity, null, new DateTimeImmutable());
        $actionEntity = new CommercialActionEntity($projectEntity, 'Relancer', new DateTimeImmutable('2026-10-12'));
        $projectEntity->addAction($actionEntity);
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('POST', '/commercial/actions/' . $actionEntity->getId() . '/postpone', ['_token' => 'invalid', 'dueOn' => '2026-10-19']);
        self::assertResponseStatusCodeSame(403);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        self::assertResponseIsSuccessful();
        $token = $crawler->filterXPath('//form[contains(@action, "/postpone")]/input[@name="_token"]')->attr('value');
        $client->request('POST', '/commercial/actions/' . $actionEntity->getId() . '/postpone', ['_token' => $token, 'dueOn' => '2026-10-19']);
        self::assertResponseRedirects();

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $token = $crawler->filterXPath('//form[contains(@action, "/complete")]/input[@name="_token"]')->attr('value');
        $client->request('POST', '/commercial/actions/' . $actionEntity->getId() . '/complete', ['_token' => $token, 'note' => 'Réponse reçue']);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedActionEntity = $entityManager->find(CommercialActionEntity::class, $actionEntity->getId());
        self::assertInstanceOf(CommercialActionEntity::class, $savedActionEntity);
        self::assertSame('2026-10-19', $savedActionEntity->getDueOn()->format('Y-m-d'));
        self::assertSame(CommercialActionEntity::STATUS_DONE, $savedActionEntity->getStatus());
        self::assertCount(2, $savedActionEntity->getProject()->getEvents());
    }

    public function testOpenActionCanBeEditedWithoutLosingItsHistory(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("action-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $projectEntity = new CommercialProjectEntity('Ateliers', $organizationEntity, null, new DateTimeImmutable());
        $actionEntity = new CommercialActionEntity($projectEntity, 'Premier appel', new DateTimeImmutable('2026-10-12'));
        $projectEntity->addAction($actionEntity);
        foreach ([$adminUserEntity, $organizationEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $token = $crawler->filterXPath('//form[contains(@action, "/edit")]/input[@name="_token"]')->attr('value');
        $client->request('POST', '/commercial/actions/' . $actionEntity->getId() . '/edit', [
            '_token' => $token,
            'title' => 'Appeler la direction',
            'details' => 'Proposer trois dates',
            'dueOn' => '2026-10-16',
        ]);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedActionEntity = $entityManager->find(CommercialActionEntity::class, $actionEntity->getId());
        self::assertInstanceOf(CommercialActionEntity::class, $savedActionEntity);
        self::assertSame('Appeler la direction', $savedActionEntity->getTitle());
        self::assertSame('Proposer trois dates', $savedActionEntity->getDetails());
        self::assertSame('2026-10-16', $savedActionEntity->getDueOn()->format('Y-m-d'));
        self::assertSame('action_edited', $savedActionEntity->getProject()->getEvents()->last()->getType());
    }
}
