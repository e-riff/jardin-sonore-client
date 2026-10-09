<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialRequestControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testManualRequestStaysToQualifyUntilOrganizationIsExplicitlyChosen(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("commercial-{$suffix}@example.test")->setPassword('unused');
        $firstOrganizationEntity = (new OrganizationEntity())->setName("Crèche des Lilas {$suffix}");
        $secondOrganizationEntity = (new OrganizationEntity())->setName("Crèche Lilas {$suffix}");
        $personEntity = (new PersonEntity())->setFirstName('Camille')->setLastName('Martin');
        $firstOrganizationEntity->addPerson($personEntity);
        foreach ([$adminUserEntity, $firstOrganizationEntity, $secondOrganizationEntity, $personEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('GET', '/commercial/requests/people?organizationId=' . $firstOrganizationEntity->getId());
        self::assertResponseIsSuccessful();
        self::assertSame(['people' => [['id' => $personEntity->getId(), 'label' => 'Camille Martin']]], json_decode((string) $client->getResponse()->getContent(), true));

        $crawler = $client->request('GET', '/commercial/requests/new');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Rechercher une structure', (string) $client->getResponse()->getContent());
        self::assertCount(1, $crawler->filterXPath('//form[@data-controller="commercial-request-contacts"]//select[@data-commercial-request-contacts-target="organization"]'));
        self::assertCount(1, $crawler->filterXPath('//form[@data-controller="commercial-request-contacts"]//select[@data-commercial-request-contacts-target="person"]'));
        self::assertStringNotContainsString('commercial_request[correctPersonFirstName]', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('commercial_request[organizationName]', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('commercial_request[city]', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('internal-button', (string) $crawler->filterXPath('//button[@type="submit"]')->attr('class'));
        $form = $crawler->selectButton('Enregistrer la demande')->form();
        $client->request('POST', '/commercial/requests/new', [
            'commercial_request' => [
                '_token' => $form['commercial_request[_token]']->getValue(),
                'organization' => (string) $firstOrganizationEntity->getId(),
                'personMode' => 'existing',
                'person' => (string) $personEntity->getId(),
                'emailAddress' => 'camille@example.test',
                'message' => 'Ateliers au printemps',
            ],
        ]);
        self::assertResponseRedirects();

        $requestEntity = static::getContainer()->get(EntityManagerInterface::class)->getRepository(CommercialRequestEntity::class)->findOneBy(['emailAddress' => 'camille@example.test'], ['id' => 'DESC']);
        self::assertInstanceOf(CommercialRequestEntity::class, $requestEntity);
        self::assertSame(CommercialRequestEntity::STATUS_TO_QUALIFY, $requestEntity->getStatus());
        self::assertSame($firstOrganizationEntity->getId(), $requestEntity->getOrganization()?->getId());
        self::assertSame($personEntity->getId(), $requestEntity->getPerson()?->getId());
        self::assertSame('Camille Martin', $requestEntity->getSenderName());
        self::assertSame($firstOrganizationEntity->getName(), $requestEntity->getOrganizationName());
        self::assertNull($requestEntity->getCity());
        self::assertCount(0, $requestEntity->getProjects());

        $crawler = $client->request('GET', '/commercial/requests/' . $requestEntity->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Ateliers au printemps', (string) $client->getResponse()->getContent());
        self::assertStringContainsString("Lilas {$suffix}", (string) $client->getResponse()->getContent());
        self::assertCount(0, $crawler->filterXPath('//input[@name="commercial_qualification[organization]" and @value="' . $firstOrganizationEntity->getId() . '"]'));
    }

    public function testQualifiedRequestCanCreateASecondProjectWithoutChangingOriginalMessage(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("commercial-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Mairie {$suffix}");
        $requestEntity = new CommercialRequestEntity('manual', 'Camille Martin', '', 'Demande initiale', new DateTimeImmutable());
        foreach ([$adminUserEntity, $organizationEntity, $requestEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        for ($number = 1; 2 >= $number; ++$number) {
            $crawler = $client->request('GET', '/commercial/requests/' . $requestEntity->getId());
            self::assertResponseIsSuccessful();
            $form = $crawler->selectButton('Créer le dossier')->form();
            $client->request('POST', '/commercial/requests/' . $requestEntity->getId(), [
                'commercial_qualification' => [
                    '_token' => $form['commercial_qualification[_token]']->getValue(),
                    'organization' => (string) $organizationEntity->getId(),
                    'newOrganizationName' => '',
                    'projectTitle' => "Projet {$number}",
                ],
            ]);
            self::assertResponseRedirects();
        }

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $savedRequestEntity = $entityManager->find(CommercialRequestEntity::class, $requestEntity->getId());
        self::assertInstanceOf(CommercialRequestEntity::class, $savedRequestEntity);
        self::assertSame(CommercialRequestEntity::STATUS_QUALIFIED, $savedRequestEntity->getStatus());
        self::assertCount(2, $savedRequestEntity->getProjects());
        self::assertSame('Demande initiale', $savedRequestEntity->getMessage());
    }

    public function testOrganizationCreationRequiresExplicitChoiceAndClosingNeedsCsrf(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("commercial-{$suffix}@example.test")->setPassword('unused');
        $requestEntity = new CommercialRequestEntity('manual', 'Alex Martin', '', 'Jardin sonore', new DateTimeImmutable(), "Nouvelle structure {$suffix}");
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($requestEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/requests/' . $requestEntity->getId());
        self::assertStringContainsString('Rechercher une structure', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('sessions.summary.form.organization_placeholder', (string) $client->getResponse()->getContent());
        $form = $crawler->selectButton('Créer le dossier')->form();
        $client->request('POST', '/commercial/requests/' . $requestEntity->getId(), [
            'commercial_qualification' => [
                '_token' => $form['commercial_qualification[_token]']->getValue(),
                'organizationMode' => 'new',
                'newOrganizationName' => "Nouvelle structure {$suffix}",
                'newPersonFirstName' => 'Alex',
                'newPersonLastName' => 'Martin',
                'newPersonRole' => 'Direction',
                'projectTitle' => 'Ateliers 2027',
            ],
        ]);
        self::assertResponseRedirects();
        $savedRequestEntity = static::getContainer()->get(EntityManagerInterface::class)->find(CommercialRequestEntity::class, $requestEntity->getId());
        self::assertInstanceOf(CommercialRequestEntity::class, $savedRequestEntity);
        self::assertCount(1, $savedRequestEntity->getProjects());
        self::assertSame("Nouvelle structure {$suffix}", $savedRequestEntity->getProjects()->first()->getOrganization()->getName());
        self::assertSame('Alex Martin', (string) $savedRequestEntity->getProjects()->first()->getPrimaryContact());

        $client->request('POST', '/commercial/requests/' . $requestEntity->getId() . '/close', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(CommercialRequestEntity::STATUS_QUALIFIED, $savedRequestEntity->getStatus());
    }

    public function testUnqualifiedRequestCanBeClassifiedWithoutResult(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("commercial-{$suffix}@example.test")->setPassword('unused');
        $requestEntity = new CommercialRequestEntity('manual', 'Alex Martin', '', 'Sans suite', new DateTimeImmutable());
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($requestEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/requests/' . $requestEntity->getId());
        $token = $crawler->filterXPath('//form[contains(@action, "/close")]/input[@name="_token"]')->attr('value');
        $client->request('POST', '/commercial/requests/' . $requestEntity->getId() . '/close', ['_token' => $token]);
        self::assertResponseRedirects();
        $savedRequestEntity = static::getContainer()->get(EntityManagerInterface::class)->find(CommercialRequestEntity::class, $requestEntity->getId());
        self::assertInstanceOf(CommercialRequestEntity::class, $savedRequestEntity);
        self::assertSame(CommercialRequestEntity::STATUS_WITHOUT_RESULT, $savedRequestEntity->getStatus());
    }

    public function testManualRequestRejectsPersonFromAnotherOrganizationAndCanCreatePerson(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("commercial-{$suffix}@example.test")->setPassword('unused');
        $firstOrganizationEntity = (new OrganizationEntity())->setName("Mairie {$suffix}");
        $otherOrganizationEntity = (new OrganizationEntity())->setName("Crèche {$suffix}");
        $otherPersonEntity = (new PersonEntity())->setFirstName('Autre')->setLastName('Contact')->setOrganization($otherOrganizationEntity);
        foreach ([$adminUserEntity, $firstOrganizationEntity, $otherOrganizationEntity, $otherPersonEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $crawler = $client->request('GET', '/commercial/requests/new');
        $form = $crawler->selectButton('Enregistrer la demande')->form();
        $token = $form['commercial_request[_token]']->getValue();
        $client->request('POST', '/commercial/requests/new', [
            'commercial_request' => [
                '_token' => $token,
                'organization' => (string) $firstOrganizationEntity->getId(),
                'personMode' => 'unknown',
                'person' => (string) $otherPersonEntity->getId(),
                'message' => 'Premier échange',
            ],
        ]);
        self::assertResponseStatusCodeSame(422);

        $client->request('POST', '/commercial/requests/new', [
            'commercial_request' => [
                '_token' => $token,
                'organization' => (string) $firstOrganizationEntity->getId(),
                'personMode' => 'new',
                'newPersonFirstName' => 'Camille',
                'newPersonLastName' => 'Martin',
                'newPersonRole' => 'Direction',
                'message' => 'Premier échange',
            ],
        ]);
        self::assertResponseRedirects();

        $requestEntity = static::getContainer()->get(EntityManagerInterface::class)->getRepository(CommercialRequestEntity::class)->findOneBy(['message' => 'Premier échange'], ['id' => 'DESC']);
        self::assertInstanceOf(CommercialRequestEntity::class, $requestEntity);
        self::assertSame($firstOrganizationEntity->getId(), $requestEntity->getOrganization()?->getId());
        self::assertSame('Camille Martin', (string) $requestEntity->getPerson());
        self::assertSame('Camille Martin', $requestEntity->getSenderName());

        $client->request('GET', '/commercial/requests/people?organizationId=' . $firstOrganizationEntity->getId());
        self::assertResponseIsSuccessful();
        $peoplePayload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $peoplePayload['people']);
        self::assertSame('Camille Martin — Direction', $peoplePayload['people'][0]['label']);

        $crawler = $client->request('GET', '/commercial/requests/new');
        $form = $crawler->selectButton('Enregistrer la demande')->form();
        $client->request('POST', '/commercial/requests/new', [
            'commercial_request' => [
                '_token' => $form['commercial_request[_token]']->getValue(),
                'organization' => (string) $firstOrganizationEntity->getId(),
                'personMode' => 'unknown',
                'emailAddress' => 'sans-nom@example.test',
                'phone' => '0601020304',
                'message' => 'Demande sans interlocuteur identifié',
            ],
        ]);
        self::assertResponseRedirects();
        $unknownRequestEntity = static::getContainer()->get(EntityManagerInterface::class)->getRepository(CommercialRequestEntity::class)->findOneBy(['emailAddress' => 'sans-nom@example.test']);
        self::assertInstanceOf(CommercialRequestEntity::class, $unknownRequestEntity);
        self::assertSame('', $unknownRequestEntity->getSenderName());
        self::assertNull($unknownRequestEntity->getPerson());
    }
}
