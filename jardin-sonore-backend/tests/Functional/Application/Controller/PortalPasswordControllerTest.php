<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Portal\IssuedPortalPasswordToken;
use App\Application\Portal\PortalPasswordTokenManager;
use App\Domain\Model\Portal\PasswordTokenType;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserPasswordTokenEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class PortalPasswordControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-sonore-portal-password-controller-cache';
            }
        };
    }

    public function testAnUnknownTokenGetsTheNeutralUnavailableResponse(): void
    {
        $client = static::createClient();
        $client->request('GET', '/portail/definir-mot-de-passe/unknown-token');

        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('Ce lien n’est plus disponible.', (string) $client->getResponse()->getContent());
    }

    public function testAShortPasswordShowsTheValidationError(): void
    {
        [$client, $issuedPortalPasswordToken] = $this->createClientWithInvitation();

        $client->request('GET', '/portail/definir-mot-de-passe/' . $issuedPortalPasswordToken->rawToken);
        $client->submitForm('Définir mon mot de passe', [
            'portal_set_password[password][first]' => 'trop-court',
            'portal_set_password[password][second]' => 'trop-court',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Le mot de passe doit contenir au moins 12 caractères.', (string) $client->getResponse()->getContent());
    }

    public function testDifferentPasswordsShowTheValidationError(): void
    {
        [$client, $issuedPortalPasswordToken] = $this->createClientWithInvitation();

        $client->request('GET', '/portail/definir-mot-de-passe/' . $issuedPortalPasswordToken->rawToken);
        $client->submitForm('Définir mon mot de passe', [
            'portal_set_password[password][first]' => 'Une phrase de passe solide',
            'portal_set_password[password][second]' => 'Une autre phrase solide',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Les mots de passe doivent être identiques.', (string) $client->getResponse()->getContent());
    }

    public function testAValidPasswordActivatesTheAccountAndConsumesTheToken(): void
    {
        [$client, $issuedPortalPasswordToken] = $this->createClientWithInvitation();

        $client->request('GET', '/portail/definir-mot-de-passe/' . $issuedPortalPasswordToken->rawToken);
        $client->submitForm('Définir mon mot de passe', [
            'portal_set_password[password][first]' => 'Une phrase de passe solide',
            'portal_set_password[password][second]' => 'Une phrase de passe solide',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Mot de passe enregistré', (string) $client->getResponse()->getContent());
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloadedUserPasswordTokenEntity = $entityManager->getRepository(UserPasswordTokenEntity::class)
            ->findOneBy(['tokenHash' => $issuedPortalPasswordToken->tokenEntity->getTokenHash()]);

        self::assertInstanceOf(UserPasswordTokenEntity::class, $reloadedUserPasswordTokenEntity);
        self::assertSame(UserStatus::ACTIVE, $reloadedUserPasswordTokenEntity->getUser()->getStatus());
        self::assertNotNull($reloadedUserPasswordTokenEntity->getConsumedAt());
    }

    public function testExpiredInvalidatedAndConsumedTokensGetTheSameNeutralResponse(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $unavailableResponses = [];

        foreach (['expired', 'invalidated', 'consumed'] as $state) {
            $rawToken = bin2hex(random_bytes(32));
            $userEntity = (new UserEntity())->setEmail("{$state}-" . bin2hex(random_bytes(8)) . '@portal.test');
            $userPasswordTokenEntity = new UserPasswordTokenEntity(
                $userEntity,
                PasswordTokenType::INVITATION,
                hash('sha256', $rawToken),
                new DateTimeImmutable('expired' === $state ? '-1 hour' : '+1 hour'),
            );
            $userEntity->addPasswordToken($userPasswordTokenEntity);
            $entityManager->persist($userEntity);
            $entityManager->flush();

            if ('invalidated' === $state) {
                $userPasswordTokenEntity->invalidateAt(new DateTimeImmutable());
                $entityManager->flush();
            } else {
                $userPasswordTokenEntity->consumeAt(new DateTimeImmutable());
                $entityManager->flush();
            }

            $client->request('GET', '/portail/definir-mot-de-passe/' . $rawToken);
            self::assertResponseStatusCodeSame(404);
            $unavailableResponses[] = (string) $client->getResponse()->getContent();
        }

        self::assertSame($unavailableResponses[0], $unavailableResponses[1]);
        self::assertSame($unavailableResponses[1], $unavailableResponses[2]);
    }

    /** @return array{0: \Symfony\Bundle\FrameworkBundle\KernelBrowser, 1: IssuedPortalPasswordToken} */
    private function createClientWithInvitation(): array
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $userEntity = (new UserEntity())->setEmail('portal-' . bin2hex(random_bytes(8)) . '@test.local');
        $entityManager->persist($userEntity);
        $entityManager->flush();
        $issuedPortalPasswordToken = static::getContainer()->get(PortalPasswordTokenManager::class)->issueInvitation($userEntity);

        return [$client, $issuedPortalPasswordToken];
    }
}
