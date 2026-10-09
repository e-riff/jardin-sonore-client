<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialProjectContactsControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testContactCanBeLinkedCorrectedAndMarkedFormerWithoutDeletingDirectoryEntry(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("contacts-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Mairie {$suffix}");
        $personEntity = (new PersonEntity())->setFirstName('Claire')->setLastName('Martin')->setOrganization($organizationEntity);
        $projectEntity = new CommercialProjectEntity('Année scolaire', $organizationEntity, null, new DateTimeImmutable());
        foreach ([$adminUserEntity, $organizationEntity, $personEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $token = $crawler->filterXPath('//form[contains(@action, "/contacts/link")]/input[@name="_token"]')->attr('value');
        $client->request('POST', '/commercial/projects/' . $projectEntity->getId() . '/contacts/link', [
            '_token' => $token,
            'personId' => (string) $personEntity->getId(),
            'primary' => '1',
        ]);
        self::assertResponseRedirects();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $savedProjectEntity = $entityManager->find(CommercialProjectEntity::class, $projectEntity->getId());
        self::assertSame($personEntity->getId(), $savedProjectEntity->getPrimaryContact()?->getId());

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $token = $crawler->filterXPath('//form[contains(@action, "/contacts/' . $personEntity->getId() . '/edit")]/input[@name="_token"]')->attr('value');
        $client->request('POST', '/commercial/projects/' . $projectEntity->getId() . '/contacts/' . $personEntity->getId() . '/edit', [
            '_token' => $token,
            'firstName' => 'Clara',
            'lastName' => 'Martin',
            'role' => 'Direction',
        ]);
        self::assertResponseRedirects();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $savedPersonEntity = $entityManager->find(PersonEntity::class, $personEntity->getId());
        self::assertSame('Clara', $savedPersonEntity->getFirstName());

        $crawler = $client->request('GET', '/commercial/projects/' . $projectEntity->getId());
        $token = $crawler->filterXPath('//form[contains(@action, "/contacts/' . $personEntity->getId() . '/former")]/input[@name="_token"]')->attr('value');
        $client->request('POST', '/commercial/projects/' . $projectEntity->getId() . '/contacts/' . $personEntity->getId() . '/former', ['_token' => $token]);
        self::assertResponseRedirects();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $savedProjectEntity = $entityManager->find(CommercialProjectEntity::class, $projectEntity->getId());
        $savedPersonEntity = $entityManager->find(PersonEntity::class, $personEntity->getId());
        self::assertNull($savedProjectEntity->getPrimaryContact());
        self::assertFalse($savedProjectEntity->getPeople()->first()->isCurrent());
        self::assertTrue($savedPersonEntity->isActive());
    }
}
