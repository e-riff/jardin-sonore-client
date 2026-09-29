<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class OrganizationPeopleNavigationTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-sonore-organization-inline-people-cache';
            }
        };
    }

    #[RunInSeparateProcess]
    public function testOrganizationDetailShowsAnEmptyPeopleStateAndKeepsTheAddAction(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Structure vide ' . bin2hex(random_bytes(8)));
        $adminUserEntity = $this->createAdmin($entityManager);
        $entityManager->persist($organizationEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('GET', "/backoffice/organization/{$organizationEntity->getId()}");

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aucune personne', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Ajouter une personne', (string) $client->getResponse()->getContent());
    }

    #[RunInSeparateProcess]
    public function testOrganizationDetailLinksEachPersonToTheirExistingDetailAndEditPages(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = $this->createAdmin($entityManager);
        $organizationEntity = (new OrganizationEntity())->setName('Structure liée ' . bin2hex(random_bytes(8)));
        $firstPersonEntity = (new PersonEntity())->setFirstName('Alice')->setLastName('Liée')->setRole('Direction <test>');
        $secondPersonEntity = (new PersonEntity())->setFirstName('Bob')->setLastName('Lié')->setRole('Accueil');
        $organizationEntity->addPerson($firstPersonEntity)->addPerson($secondPersonEntity);
        $entityManager->persist($organizationEntity);
        $entityManager->persist($firstPersonEntity);
        $entityManager->persist($secondPersonEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', "/backoffice/organization/{$organizationEntity->getId()}");

        self::assertResponseIsSuccessful();
        foreach ([$firstPersonEntity, $secondPersonEntity] as $personEntity) {
            $detailUrl = "/backoffice/person/{$personEntity->getId()}";
            $editUrl = "{$detailUrl}/edit";
            self::assertCount(1, $crawler->filterXPath("//a[contains(@href, '{$detailUrl}') and contains(., '{$personEntity->getFirstName()}')]"));
            self::assertCount(1, $crawler->filterXPath("//a[contains(@href, '{$editUrl}')]"));
            $client->request('GET', $detailUrl);
            self::assertResponseIsSuccessful();
            $client->request('GET', $editUrl);
            self::assertResponseIsSuccessful();
        }
        self::assertStringContainsString('Direction &lt;test&gt;', (string) $crawler->html());
        self::assertStringNotContainsString('Direction <test>', (string) $crawler->html());
    }

    #[RunInSeparateProcess]
    public function testOrganizationAdminCanEditPersonIdentityInlineWithCsrf(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = $this->createAdmin($entityManager);
        $organizationEntity = (new OrganizationEntity())->setName('Structure édition ' . bin2hex(random_bytes(8)));
        $personEntity = (new PersonEntity())->setFirstName('Alice')->setLastName('Initial')->setRole('Direction');
        $organizationEntity->addPerson($personEntity);
        $entityManager->persist($organizationEntity);
        $entityManager->persist($personEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $personEditUrl = "/backoffice/organization/{$organizationEntity->getId()}/people/{$personEntity->getId()}/edit";
        $crawler = $client->request('GET', "/backoffice/organization/{$organizationEntity->getId()}");
        self::assertResponseIsSuccessful();
        $tokenField = $crawler->filterXPath('//form[@action="' . $personEditUrl . '"]//input[@name="_token"]');
        self::assertCount(1, $tokenField);

        $client->request('POST', $personEditUrl, [
            '_token' => $tokenField->attr('value'),
            'firstName' => 'Alice modifiée',
            'lastName' => 'Nouveau nom',
            'role' => 'Accueil',
        ]);

        self::assertResponseRedirects();
        $updatedPersonEntity = static::getContainer()->get(EntityManagerInterface::class)->find(PersonEntity::class, $personEntity->getId());
        self::assertInstanceOf(PersonEntity::class, $updatedPersonEntity);
        self::assertSame('Alice modifiée', $updatedPersonEntity->getFirstName());
        self::assertSame('Nouveau nom', $updatedPersonEntity->getLastName());
        self::assertSame('Accueil', $updatedPersonEntity->getRole());
    }

    #[RunInSeparateProcess]
    public function testInlinePersonEditRejectsInvalidCsrfAndPeopleFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = $this->createAdmin($entityManager);
        $organizationEntity = (new OrganizationEntity())->setName('Structure autorisée ' . bin2hex(random_bytes(8)));
        $otherOrganizationEntity = (new OrganizationEntity())->setName('Structure autre ' . bin2hex(random_bytes(8)));
        $linkedPersonEntity = (new PersonEntity())->setFirstName('Bob')->setLastName('Lié');
        $personEntity = (new PersonEntity())->setFirstName('Alice')->setLastName('Privée')->setOrganization($otherOrganizationEntity);
        $organizationEntity->addPerson($linkedPersonEntity);
        foreach ([$organizationEntity, $otherOrganizationEntity, $linkedPersonEntity, $personEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('POST', "/backoffice/organization/{$organizationEntity->getId()}/people/{$linkedPersonEntity->getId()}/edit", [
            '_token' => 'invalid',
            'firstName' => 'Attaque',
            'lastName' => 'Refusée',
            'role' => '',
        ]);
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', "/backoffice/organization/{$organizationEntity->getId()}/people/{$personEntity->getId()}/edit", [
            '_token' => 'invalid',
            'firstName' => 'Attaque',
            'lastName' => 'Refusée',
            'role' => '',
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    #[RunInSeparateProcess]
    public function testPeopleIndexSearchesOrganizationFullAndPartialNamesAndStillSearchesPersonNames(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = $this->createAdmin($entityManager);
        $suffix = bin2hex(random_bytes(6));
        $organizationEntity = (new OrganizationEntity())->setName("Harmonie Céleste {$suffix}");
        $otherOrganizationEntity = (new OrganizationEntity())->setName("Maison Forestière {$suffix}");
        $matchingPersonEntity = (new PersonEntity())->setFirstName("Alice{$suffix}")->setLastName('Dupont')->setOrganization($organizationEntity);
        $otherPersonEntity = (new PersonEntity())->setFirstName("Bob{$suffix}")->setLastName('Martin')->setOrganization($otherOrganizationEntity);
        foreach ([$organizationEntity, $otherOrganizationEntity, $matchingPersonEntity, $otherPersonEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        foreach (["Harmonie Céleste {$suffix}", 'Harmonie Céleste', "Alice{$suffix}"] as $query) {
            $client->request('GET', '/backoffice/person?query=' . urlencode($query));
            self::assertResponseIsSuccessful();
            $html = (string) $client->getResponse()->getContent();
            self::assertStringContainsString("Alice{$suffix}", $html);
            self::assertStringNotContainsString("Bob{$suffix}", $html);
        }
    }

    #[RunInSeparateProcess]
    public function testOrganizationIndexSearchesByLinkedEmailAddress(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = $this->createAdmin($entityManager);
        $suffix = bin2hex(random_bytes(6));
        $organizationEntity = (new OrganizationEntity())->setName("Structure email {$suffix}");
        $otherOrganizationEntity = (new OrganizationEntity())->setName("Structure sans correspondance {$suffix}");
        $emailAddress = "direction-{$suffix}@example.test";
        $organizationEntity->getContactDetails()?->addEmailContactLink(
            (new EmailContactLinkEntity())->setEmailAddress($emailAddress),
        );
        foreach ([$organizationEntity, $otherOrganizationEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        foreach ([$emailAddress, "direction-{$suffix}"] as $query) {
            $client->request('GET', '/backoffice/organization?query=' . urlencode($query));
            self::assertResponseIsSuccessful();
            $html = (string) $client->getResponse()->getContent();
            self::assertStringContainsString("Structure email {$suffix}", $html);
            self::assertStringNotContainsString("Structure sans correspondance {$suffix}", $html);
        }
    }

    private function createAdmin(EntityManagerInterface $entityManager): AdminUserEntity
    {
        $adminUserEntity = (new AdminUserEntity())
            ->setEmail('admin-' . bin2hex(random_bytes(8)) . '@people.test')
            ->setPassword('unused');
        $entityManager->persist($adminUserEntity);

        return $adminUserEntity;
    }
}
