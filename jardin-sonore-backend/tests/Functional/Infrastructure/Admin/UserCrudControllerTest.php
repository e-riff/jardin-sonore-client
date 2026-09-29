<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Admin;

use App\Application\Portal\OrganizationAccessContactCreator;
use App\Application\Portal\OrganizationAccessEmailResolver;
use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Application\Portal\PortalImpersonationLaunchManager;
use App\Application\Portal\PortalPasswordTokenManager;
use App\Application\Portal\PortalSessionManager;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Admin\UserCrudController;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PortalImpersonationLaunchEntity;
use App\Infrastructure\Doctrine\Entity\PortalSessionEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserPasswordTokenEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class UserCrudControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-sonore-portal-admin-impersonation-cache';
            }
        };
    }

    public function testCreatingAnAccountWithANewPersonPersistsTheContactAndSendsAnInvitation(): void
    {
        static::createClient()->request('GET', '/login');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Structure ' . bin2hex(random_bytes(8)));
        $entityManager->persist($organizationEntity);
        $entityManager->flush();
        $emailAddress = 'anne.martin-' . bin2hex(random_bytes(8)) . '@portal.test';

        $portalAccountMailSender = $this->createMock(PortalAccountMailSenderInterface::class);
        $portalAccountMailSender->expects(self::once())
            ->method('sendInvitation')
            ->with(
                self::callback(static fn (UserEntity $userEntity): bool => $emailAddress === $userEntity->getEmail()),
                self::isString(),
            );
        $userCrudController = $this->createController($portalAccountMailSender);
        $userEntity = (new UserEntity())
            ->setOrganizationIdForNewAccess((string) $organizationEntity->getId())
            ->setEmail(strtoupper($emailAddress))
            ->setNewAccessContactType(UserEntity::NEW_ACCESS_CONTACT_PERSON)
            ->setNewPersonFirstNameForNewAccess('Anne')
            ->setNewPersonLastNameForNewAccess('Martin');

        $userCrudController->persistEntity($entityManager, $userEntity);
        $entityManager->clear();

        $reloadedUserEntity = $entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $emailAddress]);
        self::assertInstanceOf(UserEntity::class, $reloadedUserEntity);
        self::assertSame(UserStatus::PENDING, $reloadedUserEntity->getStatus());
        self::assertCount(1, $reloadedUserEntity->getOrganizationAccesses());
        self::assertSame('Anne Martin', (string) $reloadedUserEntity->getOrganizationAccesses()->first()?->getPerson());
        self::assertCount(1, $reloadedUserEntity->getPasswordTokens());
    }

    public function testAnSmtpFailureKeepsTheCreatedInvitationUsable(): void
    {
        static::createClient()->request('GET', '/login');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Structure ' . bin2hex(random_bytes(8)));
        $entityManager->persist($organizationEntity);
        $entityManager->flush();
        $portalAccountMailSender = $this->createStub(PortalAccountMailSenderInterface::class);
        $portalAccountMailSender->method('sendInvitation')->willThrowException(new TransportException('SMTP unavailable'));
        $userCrudController = $this->createController($portalAccountMailSender);
        $userEntity = (new UserEntity())
            ->setOrganizationIdForNewAccess((string) $organizationEntity->getId())
            ->setEmail('smtp-' . bin2hex(random_bytes(8)) . '@portal.test')
            ->setNewAccessContactType(UserEntity::NEW_ACCESS_CONTACT_ORGANIZATION);

        $userCrudController->persistEntity($entityManager, $userEntity);
        $entityManager->clear();

        $userPasswordTokenEntity = $entityManager->getRepository(UserPasswordTokenEntity::class)
            ->findOneBy(['user' => $userEntity]);
        self::assertInstanceOf(UserPasswordTokenEntity::class, $userPasswordTokenEntity);
        self::assertTrue($userPasswordTokenEntity->isUsableAt(new DateTimeImmutable()));
    }

    #[RunInSeparateProcess]
    public function testEasyAdminShowsTheCorrectPasswordActionOnIndexDetailAndEditPages(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('admin-' . bin2hex(random_bytes(8)) . '@portal.test')->setPassword('unused');
        $pendingUserEntity = (new UserEntity())->setEmail('pending-' . bin2hex(random_bytes(8)) . '@portal.test');
        $activeUserEntity = (new UserEntity())->setEmail('active-' . bin2hex(random_bytes(8)) . '@portal.test')->setStatus(UserStatus::ACTIVE);
        $inactiveUserEntity = (new UserEntity())->setEmail('inactive-' . bin2hex(random_bytes(8)) . '@portal.test')->setStatus(UserStatus::INACTIVE);
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($pendingUserEntity);
        $entityManager->persist($activeUserEntity);
        $entityManager->persist($inactiveUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('GET', '/backoffice/user?query=' . urlencode($pendingUserEntity->getEmail()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Envoyer l’invitation', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('Réinitialiser le mot de passe', (string) $client->getResponse()->getContent());
        $this->assertAccountActionForm($client->getResponse()->getContent(), $pendingUserEntity, 'send-invitation');

        $client->request('GET', '/backoffice/user?query=' . urlencode($activeUserEntity->getEmail()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Réinitialiser le mot de passe', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('Envoyer l’invitation', (string) $client->getResponse()->getContent());
        $this->assertAccountActionForm($client->getResponse()->getContent(), $activeUserEntity, 'send-password-reset');

        foreach (["/backoffice/user/{$pendingUserEntity->getId()}", "/backoffice/user/{$pendingUserEntity->getId()}/edit"] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Envoyer l’invitation', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString('Réinitialiser le mot de passe', (string) $client->getResponse()->getContent());
            $this->assertAccountActionForm($client->getResponse()->getContent(), $pendingUserEntity, 'send-invitation');
        }

        foreach (["/backoffice/user/{$activeUserEntity->getId()}", "/backoffice/user/{$activeUserEntity->getId()}/edit"] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Réinitialiser le mot de passe', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString('Envoyer l’invitation', (string) $client->getResponse()->getContent());
            $this->assertAccountActionForm($client->getResponse()->getContent(), $activeUserEntity, 'send-password-reset');
            self::assertStringContainsString('Prénom', (string) $client->getResponse()->getContent());
            self::assertStringContainsString('Nom', (string) $client->getResponse()->getContent());
            self::assertStringContainsString('Photo de profil', (string) $client->getResponse()->getContent());
        }

        $client->request('GET', "/backoffice/user/{$inactiveUserEntity->getId()}");
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Envoyer l’invitation', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('Réinitialiser le mot de passe', (string) $client->getResponse()->getContent());
    }

    #[RunInSeparateProcess]
    public function testPasswordAccountActionsRejectUnsafeRequestsAndSendExactlyOnce(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('admin-' . bin2hex(random_bytes(8)) . '@portal.test')->setPassword('unused');
        $pendingUserEntity = (new UserEntity())->setEmail('pending-' . bin2hex(random_bytes(8)) . '@portal.test');
        $activeUserEntity = (new UserEntity())->setEmail('active-' . bin2hex(random_bytes(8)) . '@portal.test')->setStatus(UserStatus::ACTIVE);
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($pendingUserEntity);
        $entityManager->persist($activeUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('GET', "/backoffice/user/{$pendingUserEntity->getId()}");
        $csrfTokenManager = static::getContainer()->get(CsrfTokenManagerInterface::class);
        $requestStack = static::getContainer()->get(RequestStack::class);
        $requestStack->push($client->getRequest());

        foreach ([[$pendingUserEntity, 'send-invitation', 'portal_invitation_'], [$activeUserEntity, 'send-password-reset', 'portal_password_reset_']] as [$userEntity, $actionPath, $csrfPrefix]) {
            $url = "/backoffice/user/{$userEntity->getId()}/{$actionPath}";
            $crossActionToken = (string) $csrfTokenManager->getToken(('send-invitation' === $actionPath ? 'portal_password_reset_' : 'portal_invitation_') . $userEntity->getId());

            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(405);
            foreach ([[], ['_token' => 'wrong'], ['_token' => $crossActionToken]] as $payload) {
                $client->request('POST', $url, $payload);
                self::assertResponseStatusCodeSame(403);
            }
            $otherUserEntity = $userEntity === $pendingUserEntity ? $activeUserEntity : $pendingUserEntity;
            $client->request('POST', "/backoffice/user/{$otherUserEntity->getId()}/{$actionPath}", ['_token' => (string) $csrfTokenManager->getToken($csrfPrefix . $otherUserEntity->getId())]);
            self::assertResponseStatusCodeSame(403);
            self::assertCount(0, $entityManager->getRepository(UserPasswordTokenEntity::class)->findBy(['user' => $userEntity]));

            $crawler = $client->request('GET', "/backoffice/user/{$userEntity->getId()}");
            $token = $crawler->filterXPath("//form[contains(@action, '/{$actionPath}')]//input[@name='_token']")->attr('value');
            self::assertNotNull($token);
            $client->request('POST', $url, ['_token' => $token], server: ['HTTP_REFERER' => 'https://attacker.example/leave']);
            self::assertResponseRedirects("/backoffice/user/{$userEntity->getId()}");
            self::assertCount(1, $entityManager->getRepository(UserPasswordTokenEntity::class)->findBy(['user' => $userEntity]));
            self::assertEmailCount(1);
            self::assertSame(
                'send-invitation' === $actionPath ? 'Invitation à votre espace Jardin Sonore' : 'Réinitialisez votre mot de passe Jardin Sonore',
                self::getMailerEvent()?->getMessage()->getSubject(),
            );
        }

        $requestStack->pop();
    }

    private function assertAccountActionForm(string $html, UserEntity $userEntity, string $actionPath): void
    {
        self::assertStringContainsString("action=\"/backoffice/user/{$userEntity->getId()}/{$actionPath}\"", $html);
        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('name="_token"', $html);
    }

    #[RunInSeparateProcess]
    public function testOnlyAnAdminCanSubmitTheCsrfProtectedImpersonationLaunchFormInANewTab(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('admin-' . bin2hex(random_bytes(8)) . '@portal.test')->setPassword('unused');
        $userEntity = (new UserEntity())->setEmail('active-' . bin2hex(random_bytes(8)) . '@portal.test')->setStatus(UserStatus::ACTIVE);
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($userEntity);
        $entityManager->flush();

        $client->request('POST', "/backoffice/user/{$userEntity->getId()}/impersonation-launch");
        self::assertResponseRedirects('/login');

        $client->loginUser($adminUserEntity);
        $indexCrawler = $client->request('GET', '/backoffice/user?query=' . urlencode($userEntity->getEmail()));
        self::assertResponseIsSuccessful();
        self::assertCount(1, $indexCrawler->filterXPath('//form[contains(@action, "/impersonation-launch")]//input[@name="_token"]'));
        $indexCsrfToken = $indexCrawler->filterXPath('//form[contains(@action, "/impersonation-launch")]//input[@name="_token"]')->attr('value');
        self::assertNotNull($indexCsrfToken);
        self::assertNotSame('', $indexCsrfToken);
        $client->request('GET', "/backoffice/user/{$userEntity->getId()}");
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("action=\"/backoffice/user/{$userEntity->getId()}/impersonation-launch\"", (string) $client->getResponse()->getContent());
        self::assertStringContainsString('method="post"', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('target="_blank"', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('name="_token"', (string) $client->getResponse()->getContent());
    }

    #[RunInSeparateProcess]
    public function testAnAdminCsrfPostIssuesOneHashedLaunchWithoutPuttingItsRawTokenInTheUrl(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('admin-' . bin2hex(random_bytes(8)) . '@portal.test')->setPassword('unused');
        $userEntity = (new UserEntity())->setEmail('active-' . bin2hex(random_bytes(8)) . '@portal.test')->setStatus(UserStatus::ACTIVE);
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($userEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);
        $portalImpersonationLaunchSchemaTool = new SchemaTool($entityManager);
        $portalImpersonationLaunchClassMetadata = $entityManager->getClassMetadata(PortalImpersonationLaunchEntity::class);
        $portalImpersonationLaunchTableCreated = !$entityManager->getConnection()->createSchemaManager()->tablesExist(['portal_impersonation_launch']);

        if ($portalImpersonationLaunchTableCreated) {
            $portalImpersonationLaunchSchemaTool->createSchema([$portalImpersonationLaunchClassMetadata]);
        }

        try {
            $client->request('POST', "/backoffice/user/{$userEntity->getId()}/impersonation-launch", ['_token' => 'invalid']);
            self::assertResponseStatusCodeSame(403);
            self::assertNull($entityManager->getRepository(PortalImpersonationLaunchEntity::class)->findOneBy(['user' => $userEntity]));

            $crawler = $client->request('GET', "/backoffice/user/{$userEntity->getId()}");
            $csrfToken = $crawler->filterXPath('//form[contains(@action, "/impersonation-launch")]//input[@name="_token"]')->attr('value');
            $client->request('POST', "/backoffice/user/{$userEntity->getId()}/impersonation-launch", ['_token' => $csrfToken]);
            self::assertResponseIsSuccessful();
            preg_match('/name="launchToken" value="([^"]+)"/', (string) $client->getResponse()->getContent(), $matches);
            self::assertArrayHasKey(1, $matches);
            $rawLaunchToken = $matches[1];
            $portalImpersonationLaunchEntity = $entityManager->getRepository(PortalImpersonationLaunchEntity::class)->findOneBy(['user' => $userEntity]);
            self::assertInstanceOf(PortalImpersonationLaunchEntity::class, $portalImpersonationLaunchEntity);
            self::assertSame(hash('sha256', $rawLaunchToken), $portalImpersonationLaunchEntity->getTokenHash());
            self::assertStringNotContainsString($rawLaunchToken, $client->getRequest()->getUri());
        } finally {
            if ($portalImpersonationLaunchTableCreated) {
                $portalImpersonationLaunchSchemaTool->dropSchema([$portalImpersonationLaunchClassMetadata]);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testPersistingAnInactiveStatusOutsideTheControllerInvalidatesPortalArtifacts(): void
    {
        static::createClient()->request('GET', '/login');
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('admin-' . bin2hex(random_bytes(8)) . '@portal.test')->setPassword('unused');
        $userEntity = (new UserEntity())->setEmail('active-' . bin2hex(random_bytes(8)) . '@portal.test')->setStatus(UserStatus::ACTIVE);
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($userEntity);
        $entityManager->flush();
        $portalImpersonationLaunchSchemaTool = new SchemaTool($entityManager);
        $portalImpersonationLaunchClassMetadata = $entityManager->getClassMetadata(PortalImpersonationLaunchEntity::class);
        $portalImpersonationLaunchTableCreated = !$entityManager->getConnection()->createSchemaManager()->tablesExist(['portal_impersonation_launch']);

        if ($portalImpersonationLaunchTableCreated) {
            $portalImpersonationLaunchSchemaTool->createSchema([$portalImpersonationLaunchClassMetadata]);
        }

        try {
            $container->get(PortalSessionManager::class)->create($userEntity);
            $container->get(PortalImpersonationLaunchManager::class)->issue($userEntity, $adminUserEntity);
            $userEntity->setStatus(UserStatus::INACTIVE);
            $entityManager->flush();
            $entityManager->clear();

            $portalSessionEntity = $entityManager->getRepository(PortalSessionEntity::class)->findOneBy(['user' => $userEntity]);
            $portalImpersonationLaunchEntity = $entityManager->getRepository(PortalImpersonationLaunchEntity::class)->findOneBy(['user' => $userEntity]);
            self::assertInstanceOf(PortalSessionEntity::class, $portalSessionEntity);
            self::assertNotNull($portalSessionEntity->getRevokedAt());
            self::assertInstanceOf(PortalImpersonationLaunchEntity::class, $portalImpersonationLaunchEntity);
            self::assertNotNull($portalImpersonationLaunchEntity->getInvalidatedAt());
        } finally {
            if ($portalImpersonationLaunchTableCreated) {
                $portalImpersonationLaunchSchemaTool->dropSchema([$portalImpersonationLaunchClassMetadata]);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testFailedStatusUpdateDoesNotInvalidatePortalArtifacts(): void
    {
        static::createClient()->request('GET', '/login');
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('admin-' . bin2hex(random_bytes(8)) . '@portal.test')->setPassword('unused');
        $userEntity = (new UserEntity())->setEmail('active-' . bin2hex(random_bytes(8)) . '@portal.test')->setStatus(UserStatus::ACTIVE);
        $duplicateEmailUserEntity = (new UserEntity())->setEmail('duplicate-' . bin2hex(random_bytes(8)) . '@portal.test')->setStatus(UserStatus::ACTIVE);
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($userEntity);
        $entityManager->persist($duplicateEmailUserEntity);
        $entityManager->flush();
        $portalImpersonationLaunchSchemaTool = new SchemaTool($entityManager);
        $portalImpersonationLaunchClassMetadata = $entityManager->getClassMetadata(PortalImpersonationLaunchEntity::class);
        $portalImpersonationLaunchTableCreated = !$entityManager->getConnection()->createSchemaManager()->tablesExist(['portal_impersonation_launch']);

        if ($portalImpersonationLaunchTableCreated) {
            $portalImpersonationLaunchSchemaTool->createSchema([$portalImpersonationLaunchClassMetadata]);
        }

        try {
            $container->get(PortalSessionManager::class)->create($userEntity);
            $container->get(PortalImpersonationLaunchManager::class)->issue($userEntity, $adminUserEntity);
            $userEntity->setStatus(UserStatus::INACTIVE);
            $userEntity->setEmail($duplicateEmailUserEntity->getEmail());

            try {
                $entityManager->flush();
                self::fail('The duplicate email must make the status update fail.');
            } catch (UniqueConstraintViolationException) {
            }

            $container->get(ManagerRegistry::class)->resetManager();
            $reloadedEntityManager = $container->get(EntityManagerInterface::class);
            $reloadedUserEntity = $reloadedEntityManager->getRepository(UserEntity::class)->find($userEntity->getId());
            $portalSessionEntity = $reloadedEntityManager->getRepository(PortalSessionEntity::class)->findOneBy(['user' => $reloadedUserEntity]);
            $portalImpersonationLaunchEntity = $reloadedEntityManager->getRepository(PortalImpersonationLaunchEntity::class)->findOneBy(['user' => $reloadedUserEntity]);
            self::assertInstanceOf(UserEntity::class, $reloadedUserEntity);
            self::assertSame(UserStatus::ACTIVE, $reloadedUserEntity->getStatus());
            self::assertInstanceOf(PortalSessionEntity::class, $portalSessionEntity);
            self::assertNull($portalSessionEntity->getRevokedAt());
            self::assertInstanceOf(PortalImpersonationLaunchEntity::class, $portalImpersonationLaunchEntity);
            self::assertNull($portalImpersonationLaunchEntity->getInvalidatedAt());
        } finally {
            if ($portalImpersonationLaunchTableCreated) {
                $portalImpersonationLaunchSchemaTool->dropSchema([$portalImpersonationLaunchClassMetadata]);
            }
        }
    }

    private function createController(PortalAccountMailSenderInterface $portalAccountMailSender): UserCrudController
    {
        $container = static::getContainer();
        $request = Request::create('/backoffice/user');
        $request->setSession(new Session(new MockFileSessionStorage()));
        $container->get(RequestStack::class)->push($request);
        $userCrudController = new UserCrudController(
            $container->get(EntityManagerInterface::class),
            $container->get(EmailContactDoctrineRepository::class),
            $container->get(OrganizationAccessContactCreator::class),
            $container->get(OrganizationAccessEmailResolver::class),
            $container->get(PortalPasswordTokenManager::class),
            $portalAccountMailSender,
            $container->get(PortalImpersonationLaunchManager::class),
            $container->get(AdminUrlGenerator::class),
            'https://jardin-sonore.example.test',
        );
        $userCrudController->setContainer($container);

        return $userCrudController;
    }
}
