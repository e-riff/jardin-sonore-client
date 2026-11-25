<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class PortalUserEmailAutocompleteTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel('test', true);
    }

    public function testOffersOrganizationEmailsWithoutTypingAndKeepsOrganizationsSeparate(): void
    {
        $client = static::createClient();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));
        $admin = (new AdminUserEntity())->setEmail("admin-{$suffix}@example.test")->setPassword('unused');
        $organization = (new OrganizationEntity())->setName("Structure {$suffix}");
        $other = (new OrganizationEntity())->setName("Autre {$suffix}");
        $inactivePerson = (new PersonEntity())->setFirstName('Ancien')->setLastName('Contact')->setActive(false);
        $inactivePerson->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailAddress("inactive-{$suffix}@example.test"));
        $organization->addPerson($inactivePerson);
        $email = "creche-{$suffix}@example.test";
        $organization->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailAddress($email));
        $other->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailAddress("autre-{$suffix}@example.test"));
        foreach ([$admin, $organization, $other, $inactivePerson] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
        $client->loginUser($admin);
        $client->request('GET', '/technical/portal-users/linked-email-autocomplete', ['organizationId' => $organization->getId()]);
        self::assertResponseIsSuccessful();
        $result = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([$email], array_column($result['items'], 'email'));
        $client->request('GET', '/technical/portal-users/linked-email-autocomplete', ['organizationId' => $organization->getId(), 'q' => 'creche']);
        $result = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([$email], array_column($result['items'], 'email'));
        $client->request('GET', '/technical/portal-users/linked-email-autocomplete', ['organizationId' => $other->getId()]);
        $result = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotContains($email, array_column($result['items'], 'email'));
    }

    public function testRejectsDifferentSelectedAndTypedEmail(): void
    {
        $client = static::createClient();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));
        $admin = (new AdminUserEntity())->setEmail("admin-mismatch-{$suffix}@example.test")->setPassword('unused');
        $organization = (new OrganizationEntity())->setName("Structure {$suffix}");
        $linked = "linked-{$suffix}@example.test";
        $typed = "typed-{$suffix}@example.test";
        $organization->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailAddress($linked));
        $manager->persist($admin);
        $manager->persist($organization);
        $manager->flush();
        $client->loginUser($admin);
        $client->request('GET', '/technical/portal-users/new');
        $client->submitForm('Créer le compte', [
            'portal_user[organizationIdForNewAccess]' => (string) $organization->getId(),
            'portal_user[linkedEmailAddressForNewAccess]' => $linked,
            'portal_user[email]' => $typed,
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($manager->getRepository(UserEntity::class)->findOneBy(['email' => $linked]));
        self::assertNull($manager->getRepository(UserEntity::class)->findOneBy(['email' => $typed]));
    }
}
