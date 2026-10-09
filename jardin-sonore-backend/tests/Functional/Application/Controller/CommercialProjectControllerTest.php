<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use DateTimeImmutable;
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
}
