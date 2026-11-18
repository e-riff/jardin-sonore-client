<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\Exception\TransportException;

final class CommercialProjectPortalAccessTest extends WebTestCase
{
    /** @var list<string> */
    private array $invitedEmails = [];

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testKnownPersonReceivesAnInvitationAndRepeatedSubmissionCreatesNoDuplicate(): void
    {
        [$client, $projectId, $email] = $this->fixture();
        $crawler = $client->request('GET', "/commercial/projects/{$projectId}");
        $form = $crawler->filterXPath('//form[@name="commercial_project_portal_access"]')->form([
            'commercial_project_portal_access[knownEmail]' => $this->knownEmailValue($crawler, $email),
        ]);
        $client->submit($form);
        self::assertResponseRedirects("/commercial/projects/{$projectId}", 303);
        $userEntity = $this->user($email);
        self::assertSame(UserStatus::PENDING, $userEntity->getStatus());
        self::assertSame('Camille', $userEntity->getFirstName());
        self::assertCount(1, $userEntity->getOrganizationAccesses());
        self::assertSame('Camille', $userEntity->getOrganizationAccesses()->first()->getPerson()?->getFirstName());
        self::assertCount(1, $userEntity->getPasswordTokens());
        self::assertSame([$email], $this->invitedEmails);
        $client->submit($form);
        self::assertResponseRedirects("/commercial/projects/{$projectId}", 303);
        self::assertCount(1, $this->user($email)->getOrganizationAccesses());
        self::assertCount(1, $this->user($email)->getPasswordTokens());
        self::assertSame([$email], $this->invitedEmails);
    }

    #[DataProvider('sharedMailboxStates')]
    public function testSharedMailboxKeepsTheSelectedPersonEvenWhenAnotherPersonIsInactive(bool $inactive): void
    {
        [$client, $projectId, $email] = $this->fixture();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $projectEntity = $entityManager->find(CommercialProjectEntity::class, $projectId);
        $organizationEntity = $projectEntity->getOrganization();
        $organizationEntity->getPeople()->first()->setActive(!$inactive);
        $personEntity = (new PersonEntity())->setFirstName('Alex')->setLastName('Durand');
        $personEntity->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailAddress($email));
        $organizationEntity->addPerson($personEntity);
        $entityManager->persist($personEntity);
        $entityManager->flush();
        $crawler = $client->request('GET', "/commercial/projects/{$projectId}");
        $client->submit($crawler->filterXPath('//form[@name="commercial_project_portal_access"]')->form([
            'commercial_project_portal_access[knownEmail]' => $crawler->filterXPath('//select[@name="commercial_project_portal_access[knownEmail]"]/option[contains(., "Alex")]')->attr('value'),
        ]));
        self::assertResponseRedirects("/commercial/projects/{$projectId}", 303);
        self::assertSame('Alex', $this->user($email)->getFirstName());
        self::assertSame('Alex', $this->user($email)->getOrganizationAccesses()->first()->getPerson()?->getFirstName());
        self::assertSame([$email], $this->invitedEmails);
    }

    public static function sharedMailboxStates(): iterable
    {
        yield 'both active' => [false];
        yield 'first inactive' => [true];
    }

    public function testInvitationTransportFailureKeepsCreatedAccountAndShowsRecoveryMessage(): void
    {
        [$client, $projectId, $email] = $this->fixture(failInvitation: true);
        $crawler = $client->request('GET', "/commercial/projects/{$projectId}");
        $client->submit($crawler->filterXPath('//form[@name="commercial_project_portal_access"]')->form([
            'commercial_project_portal_access[knownEmail]' => $this->knownEmailValue($crawler, $email),
        ]));
        self::assertResponseRedirects("/commercial/projects/{$projectId}", 303);
        self::assertCount(1, $this->user($email)->getOrganizationAccesses());
        self::assertCount(1, $this->user($email)->getPasswordTokens());
        $client->followRedirect();
        self::assertStringContainsString('l’invitation n’a pas pu être envoyée', $client->getCrawler()->filterXPath('//main')->text());
    }

    public function testFreeEmailCreatesAnOrganizationContactAndPortalAccount(): void
    {
        [$client, $projectId] = $this->fixture();
        $email = 'free-' . bin2hex(random_bytes(5)) . '@example.test';
        $crawler = $client->request('GET', "/commercial/projects/{$projectId}");
        $client->submit($crawler->filterXPath('//form[@name="commercial_project_portal_access"]')->form([
            'commercial_project_portal_access[freeEmail]' => strtoupper($email),
        ]));
        self::assertResponseRedirects("/commercial/projects/{$projectId}", 303);
        $access = $this->user($email)->getOrganizationAccesses()->first();
        self::assertNull($access->getPerson());
        self::assertContains($email, array_map(static fn ($contact): string => $contact->getEmailAddress(), $access->getOrganization()->getContactDetails()->getEmailContacts()->toArray()));
        self::assertSame([$email], $this->invitedEmails);
    }

    public function testExistingAccountGainsStructureAccessWithoutNewInvitation(): void
    {
        [$client, $projectId, $email] = $this->fixture(UserStatus::ACTIVE);
        $crawler = $client->request('GET', "/commercial/projects/{$projectId}");
        $client->submit($crawler->filterXPath('//form[@name="commercial_project_portal_access"]')->form([
            'commercial_project_portal_access[knownEmail]' => $this->knownEmailValue($crawler, $email),
        ]));
        self::assertResponseRedirects("/commercial/projects/{$projectId}", 303);
        self::assertCount(1, $this->user($email)->getOrganizationAccesses());
        self::assertSame(UserStatus::ACTIVE, $this->user($email)->getStatus());
        self::assertCount(0, $this->user($email)->getPasswordTokens());
        self::assertSame([], $this->invitedEmails);
    }

    #[DataProvider('invalidAccountStates')]
    public function testDisabledAccountsAndAccessesAreNotReactivated(UserStatus $status, bool $disabledAccess): void
    {
        [$client, $projectId, $email] = $this->fixture($status, $disabledAccess);
        $crawler = $client->request('GET', "/commercial/projects/{$projectId}");
        $client->submit($crawler->filterXPath('//form[@name="commercial_project_portal_access"]')->form([
            'commercial_project_portal_access[knownEmail]' => $this->knownEmailValue($crawler, $email),
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertCount(1, $client->getCrawler()->filterXPath('//*[@data-internal-dialog-open-on-connect-value="true"]'));
        self::assertSame($status, $this->user($email)->getStatus());
        self::assertCount($disabledAccess ? 1 : 0, $this->user($email)->getOrganizationAccesses());
        if ($disabledAccess) {
            self::assertFalse($this->user($email)->getOrganizationAccesses()->first()->isActive());
        }
        self::assertSame([], $this->invitedEmails);
    }

    public static function invalidAccountStates(): iterable
    {
        yield 'inactive account' => [UserStatus::INACTIVE, false];
        yield 'disabled access' => [UserStatus::ACTIVE, true];
    }

    #[DataProvider('invalidSelections')]
    public function testInvalidSelectionDoesNotCreateAnyAccount(string $selection): void
    {
        [$client, $projectId, $email] = $this->fixture();
        $crawler = $client->request('GET', "/commercial/projects/{$projectId}");
        $form = $crawler->filterXPath('//form[@name="commercial_project_portal_access"]')->form();
        $values = $form->getPhpValues();
        $values['commercial_project_portal_access'] = array_replace($values['commercial_project_portal_access'], match ($selection) {
            'both' => ['knownEmail' => $this->knownEmailValue($crawler, $email), 'freeEmail' => 'other@example.test'],
            'invalid' => ['freeEmail' => 'invalid'],
            'foreign' => ['knownEmail' => 'foreign@example.test'],
            'csrf' => ['knownEmail' => $this->knownEmailValue($crawler, $email), '_token' => 'invalid'],
            default => [],
        });
        $client->request('POST', "/commercial/projects/{$projectId}", $values);
        self::assertResponseStatusCodeSame(422);
        self::assertNull(self::getContainer()->get(EntityManagerInterface::class)->getRepository(UserEntity::class)->findOneBy(['email' => $email]));
        self::assertSame([], $this->invitedEmails);
    }

    public static function invalidSelections(): iterable
    {
        foreach (['empty', 'both', 'invalid', 'foreign', 'csrf'] as $selection) {
            yield $selection => [$selection];
        }
    }

    public function testAnonymousUserCannotCreateAccess(): void
    {
        $client = self::createClient();
        $client->request('POST', '/commercial/projects/1', ['commercial_project_portal_access' => ['freeEmail' => 'test@example.test']]);
        self::assertResponseRedirects('/login');
    }

    /** @return array{KernelBrowser, int, string} */
    private function fixture(?UserStatus $status = null, bool $disabledAccess = false, bool $failInvitation = false): array
    {
        $client = self::createClient();
        $client->disableReboot();
        $invitedEmails = &$this->invitedEmails;
        self::getContainer()->set(PortalAccountMailSenderInterface::class, new class($invitedEmails, $failInvitation) implements PortalAccountMailSenderInterface {
            /** @param list<string> $emails */
            public function __construct(private array &$emails, private bool $failInvitation)
            {
            }

            public function sendInvitation(UserEntity $userEntity, string $rawToken): void
            {
                if ($this->failInvitation) {
                    throw new TransportException('Test transport unavailable.');
                }
                $this->emails[] = $userEntity->getEmail();
            }

            public function sendPasswordReset(UserEntity $userEntity, string $rawToken): void
            {
                throw new LogicException('Unexpected password reset.');
            }
        });
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $email = "person-{$suffix}@example.test";
        $adminUserEntity = (new AdminUserEntity())->setEmail("admin-access-{$suffix}@example.test")->setPassword('unused');
        $organizationEntity = (new OrganizationEntity())->setName("Structure accès {$suffix}");
        $personEntity = (new PersonEntity())->setFirstName('Camille')->setLastName('Martin');
        $personEntity->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailAddress($email));
        $organizationEntity->addPerson($personEntity);
        $projectEntity = new CommercialProjectEntity('Dossier accès', $organizationEntity, null, new DateTimeImmutable());
        foreach ([$adminUserEntity, $organizationEntity, $personEntity, $projectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        if (null !== $status) {
            $userEntity = (new UserEntity())->setEmail($email)->setStatus($status);
            if ($disabledAccess) {
                $userEntity->addOrganizationAccess((new UserOrganizationAccessEntity())->setOrganization($organizationEntity)->setActive(false));
            }
            $entityManager->persist($userEntity);
        }
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        return [$client, $projectEntity->getId(), $email];
    }

    private function knownEmailValue(Crawler $crawler, string $email): string
    {
        return $crawler->filterXPath('//select[@name="commercial_project_portal_access[knownEmail]"]/option[contains(., "' . $email . '")]')->first()->attr('value');
    }

    private function user(string $email): UserEntity
    {
        $userEntity = self::getContainer()->get(EntityManagerInterface::class)->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(UserEntity::class, $userEntity);

        return $userEntity;
    }
}
