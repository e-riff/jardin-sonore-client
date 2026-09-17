<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Admin;

use App\Application\Portal\OrganizationAccessContactCreator;
use App\Application\Portal\OrganizationAccessEmailResolver;
use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Application\Portal\PortalImpersonationLaunchManager;
use App\Application\Portal\PortalPasswordTokenManager;
use App\Application\Portal\PortalSessionManager;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Admin\UserCrudController;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCrudControllerTest extends TestCase
{
    public function testItDelegatesDisabledAccountInvalidationToTheDoctrineLifecycle(): void
    {
        $userEntity = (new UserEntity())->setStatus(UserStatus::INACTIVE);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($userEntity);
        $entityManager->expects(self::once())->method('flush');
        $clock = new MockClock();
        $portalSessionManager = new PortalSessionManager($entityManager, $clock, 604800);
        $portalImpersonationLaunchManager = new PortalImpersonationLaunchManager(
            $entityManager,
            $clock,
            $portalSessionManager,
            $this->createStub(LoggerInterface::class),
            300,
            1800,
        );
        $emailContactDoctrineRepository = (new ReflectionClass(EmailContactDoctrineRepository::class))->newInstanceWithoutConstructor();
        $portalPasswordTokenManager = new PortalPasswordTokenManager(
            $entityManager,
            $clock,
            $this->createStub(UserPasswordHasherInterface::class),
            86400,
        );
        $userCrudController = new UserCrudController(
            $entityManager,
            $emailContactDoctrineRepository,
            new OrganizationAccessContactCreator(),
            new OrganizationAccessEmailResolver(),
            $portalPasswordTokenManager,
            $this->createStub(PortalAccountMailSenderInterface::class),
            $portalImpersonationLaunchManager,
            'https://jardin-sonore.example.test',
        );

        $userCrudController->updateEntity($entityManager, $userEntity);
    }
}
