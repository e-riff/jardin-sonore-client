<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;

final class OrganizationContactActionsTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-sonore-organization-contact-actions-cache';
            }
        };
    }

    #[RunInSeparateProcess]
    public function testOrganizationCanAddAnEmailThroughItsDetailAction(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Structure email ' . bin2hex(random_bytes(8)));
        $adminUserEntity = $this->createAdmin($entityManager);
        $entityManager->persist($organizationEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $detailCrawler = $client->request('GET', "/backoffice/organization/{$organizationEntity->getId()}");
        self::assertResponseIsSuccessful();
        $emailUrl = $this->findActionUrl($detailCrawler, 'email-contact-link');
        self::assertNotNull($emailUrl);

        $formCrawler = $client->request('GET', (string) $emailUrl);
        self::assertResponseIsSuccessful();
        $form = $formCrawler->filterXPath('//input[contains(@name, "emailAddress")]/ancestor::form[1]')->form();
        $emailField = $formCrawler->filterXPath('//input[contains(@name, "emailAddress")]');
        $typeField = $formCrawler->filterXPath('//select[contains(@name, "type")]');
        $form->setValues([
            (string) $emailField->attr('name') => 'structure-' . bin2hex(random_bytes(5)) . '@example.test',
            (string) $typeField->attr('name') => '0',
        ]);
        $client->submit($form);

        $this->assertRedirectedOrShowFormErrors($client);
    }

    #[RunInSeparateProcess]
    public function testAddingAnEmailAlreadyUsedByAnotherOrganizationReusesTheSharedContact(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $emailAddress = 'shared-' . bin2hex(random_bytes(5)) . '@example.test';
        $sourceOrganizationEntity = (new OrganizationEntity())->setName('Structure source email');
        $targetOrganizationEntity = (new OrganizationEntity())->setName('Structure destination email');
        $sourceEmailContactLinkEntity = (new EmailContactLinkEntity())->setEmailAddress($emailAddress);
        $sourceOrganizationEntity->getContactDetails()?->addEmailContactLink($sourceEmailContactLinkEntity);
        $adminUserEntity = $this->createAdmin($entityManager);
        foreach ([$sourceOrganizationEntity, $targetOrganizationEntity] as $organizationEntity) {
            $entityManager->persist($organizationEntity);
        }
        $entityManager->flush();
        $sourceEmailContactId = $sourceEmailContactLinkEntity->getEmailContact()?->getId();
        self::assertNotNull($sourceEmailContactId);
        $client->loginUser($adminUserEntity);

        $detailCrawler = $client->request('GET', "/backoffice/organization/{$targetOrganizationEntity->getId()}");
        self::assertResponseIsSuccessful();
        $emailUrl = $this->findActionUrl($detailCrawler, 'email-contact-link');
        $formCrawler = $client->request('GET', $emailUrl);
        self::assertResponseIsSuccessful();
        $form = $formCrawler->filterXPath('//input[contains(@name, "emailAddress")]/ancestor::form[1]')->form();
        $emailField = $formCrawler->filterXPath('//input[contains(@name, "emailAddress")]');
        $typeField = $formCrawler->filterXPath('//select[contains(@name, "type")]');
        $form->setValues([
            (string) $emailField->attr('name') => $emailAddress,
            (string) $typeField->attr('name') => '0',
        ]);
        $client->submit($form);

        self::assertResponseRedirects();
        $freshEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $freshTargetOrganizationEntity = $freshEntityManager->find(OrganizationEntity::class, $targetOrganizationEntity->getId());
        self::assertInstanceOf(OrganizationEntity::class, $freshTargetOrganizationEntity);
        self::assertCount(1, $freshTargetOrganizationEntity->getContactDetails()?->getEmailContactLinks());
        $targetEmailContactLinkEntities = $freshEntityManager->getRepository(EmailContactLinkEntity::class)->findBy([
            'contactDetails' => $freshTargetOrganizationEntity->getContactDetails(),
        ]);
        self::assertCount(1, $targetEmailContactLinkEntities);
        $targetEmailContactLinkEntity = $targetEmailContactLinkEntities[0];
        self::assertInstanceOf(EmailContactLinkEntity::class, $targetEmailContactLinkEntity);
        self::assertSame($sourceEmailContactId, $targetEmailContactLinkEntity->getEmailContact()?->getId());
    }

    #[RunInSeparateProcess]
    public function testOrganizationCanAddAnAddressThroughItsDetailAction(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Structure adresse ' . bin2hex(random_bytes(8)));
        $adminUserEntity = $this->createAdmin($entityManager);
        $entityManager->persist($organizationEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $detailCrawler = $client->request('GET', "/backoffice/organization/{$organizationEntity->getId()}");
        self::assertResponseIsSuccessful();
        $addressUrl = $this->findActionUrl($detailCrawler, 'address-contact');
        self::assertNotNull($addressUrl);

        $formCrawler = $client->request('GET', (string) $addressUrl);
        self::assertResponseIsSuccessful();
        $form = $formCrawler->filterXPath('//textarea[contains(@name, "address")]/ancestor::form[1]')->form();
        $addressField = $formCrawler->filterXPath('//textarea[contains(@name, "address")]');
        $postalCodeField = $formCrawler->filterXPath('//input[contains(@name, "postalCode")]');
        $cityField = $formCrawler->filterXPath('//input[contains(@name, "city")]');
        $typeField = $formCrawler->filterXPath('//select[contains(@name, "type")]');
        $form->setValues([
            (string) $addressField->attr('name') => '12 rue du Jardin',
            (string) $postalCodeField->attr('name') => '75001',
            (string) $cityField->attr('name') => 'Paris',
            (string) $typeField->attr('name') => '0',
        ]);
        $client->submit($form);

        $this->assertRedirectedOrShowFormErrors($client);
    }

    private function createAdmin(EntityManagerInterface $entityManager): AdminUserEntity
    {
        $adminUserEntity = (new AdminUserEntity())
            ->setEmail('admin-' . bin2hex(random_bytes(8)) . '@contacts.test')
            ->setPassword('unused');
        $entityManager->persist($adminUserEntity);

        return $adminUserEntity;
    }

    private function findActionUrl(Crawler $crawler, string $action): string
    {
        foreach ($crawler->filterXPath('//a') as $link) {
            $href = $link->getAttribute('href');
            if (str_contains($href, 'contactDetailsId') && str_contains($href, $action)) {
                return $href;
            }
        }

        $links = [];
        foreach ($crawler->filterXPath('//a') as $link) {
            $links[] = $link->getAttribute('href') . ' ' . trim($link->textContent);
        }

        self::fail("Could not find the {$action} action on the organization detail page: " . implode(' | ', $links));
    }

    private function assertRedirectedOrShowFormErrors(KernelBrowser $client): void
    {
        $errors = [];
        foreach ($client->getCrawler()->filterXPath('//*[contains(@class, "invalid-feedback") or contains(@class, "form-error-message")]') as $error) {
            $errors[] = trim($error->textContent);
        }

        self::assertSame(
            302,
            $client->getResponse()->getStatusCode(),
            implode('; ', $errors),
        );
    }
}
