<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Admin;

use App\Application\Portal\OrganizationAccessContactCreator;
use App\Application\Portal\OrganizationAccessEmailResolver;
use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Application\Portal\PortalPasswordTokenManager;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Admin\UserCrudController;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserPasswordTokenEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\Exception\TransportException;

final class UserCrudControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-sonore-portal-admin-cache';
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
            ->setOrganizationForNewAccess($organizationEntity)
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
            ->setOrganizationForNewAccess($organizationEntity)
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

        $client->request('GET', '/backoffice/user');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Envoyer l’invitation', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Réinitialiser le mot de passe', (string) $client->getResponse()->getContent());

        foreach (["/backoffice/user/{$pendingUserEntity->getId()}", "/backoffice/user/{$pendingUserEntity->getId()}/edit"] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Envoyer l’invitation', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString('Réinitialiser le mot de passe', (string) $client->getResponse()->getContent());
        }

        foreach (["/backoffice/user/{$activeUserEntity->getId()}", "/backoffice/user/{$activeUserEntity->getId()}/edit"] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Réinitialiser le mot de passe', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString('Envoyer l’invitation', (string) $client->getResponse()->getContent());
        }

        $client->request('GET', "/backoffice/user/{$inactiveUserEntity->getId()}");
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Envoyer l’invitation', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('Réinitialiser le mot de passe', (string) $client->getResponse()->getContent());
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
        );
        $userCrudController->setContainer($container);

        return $userCrudController;
    }
}
