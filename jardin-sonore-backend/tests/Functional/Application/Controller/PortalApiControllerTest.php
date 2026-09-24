<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Portal\PortalPasswordTokenManager;
use App\Application\Portal\PortalSessionManager;
use App\Domain\Model\Portal\UserStatus;
use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Model\Session\SessionDocumentStatus;
use App\Domain\Model\Session\SessionSequenceSourceKind;
use App\Domain\Model\Session\SessionSequenceType;
use App\Infrastructure\Doctrine\Entity\InstrumentEntity;
use App\Infrastructure\Doctrine\Entity\MediaResourceEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class PortalApiControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): \Symfony\Component\HttpKernel\KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-sonore-portal-api-controller-cache';
            }
        };
    }

    public function testLoginIssuesASessionAndExposesOnlyAuthorizedOrganizations(): void
    {
        [$client, $userEntity, $organizationEntity] = $this->createActiveUserWithOrganization();

        $this->requestJson($client, 'POST', '/api/portal/auth/login', [
            'email' => $userEntity->getEmail(),
            'password' => 'Une phrase de passe solide',
        ]);

        self::assertResponseIsSuccessful();
        $response = $this->responseJson($client);
        self::assertIsString($response['token'] ?? null);

        $client->request('GET', '/api/portal/me', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $response['token']]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'email' => $userEntity->getEmail(),
            'firstName' => null,
            'lastName' => null,
            'avatarPath' => null,
            'newSessionNotificationsEnabled' => false,
            'organizations' => [[
                'uuid' => $organizationEntity->getUuid()->toRfc4122(),
                'name' => $organizationEntity->getName(),
            ]],
        ], $this->responseJson($client));
    }

    public function testInvalidLoginIsRejected(): void
    {
        [$client, $userEntity] = $this->createActiveUserWithOrganization();

        $this->requestJson($client, 'POST', '/api/portal/auth/login', [
            'email' => $userEntity->getEmail(),
            'password' => 'mot de passe incorrect',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['message' => 'Invalid credentials.'], $this->responseJson($client));
    }

    public function testAuthenticatedUserCanUploadAnAvatar(): void
    {
        [$client, $userEntity] = $this->createActiveUserWithOrganization();
        $token = $this->login($client, $userEntity);
        $temporaryImagePath = tempnam(sys_get_temp_dir(), 'portal-avatar-');
        self::assertIsString($temporaryImagePath);
        file_put_contents($temporaryImagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL1rgAAAABJRU5ErkJggg==', true));

        try {
            $client->request('POST', '/api/portal/me/avatar', files: [
                'avatar' => new UploadedFile($temporaryImagePath, 'avatar.png', 'image/png', null, true),
            ], server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);

            self::assertResponseIsSuccessful();
            $response = $this->responseJson($client);
            self::assertMatchesRegularExpression('#^[0-9a-f-]+\\.png$#', (string) ($response['avatarPath'] ?? ''));
        } finally {
            @unlink($temporaryImagePath);
        }
    }

    public function testAuthenticatedUserCanChooseToReceiveNewSessionNotifications(): void
    {
        [$client, $userEntity] = $this->createActiveUserWithOrganization();
        $token = $this->login($client, $userEntity);

        $this->requestJson($client, 'PATCH', '/api/portal/me/profile', [
            'firstName' => 'Anaïs',
            'lastName' => 'Martin',
            'newSessionNotificationsEnabled' => true,
        ], ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->responseJson($client)['newSessionNotificationsEnabled']);
    }

    public function testPasswordResetRequestHasTheSameNeutralResponseForKnownAndUnknownAddresses(): void
    {
        [$client, $userEntity] = $this->createActiveUserWithOrganization();

        $this->requestJson($client, 'POST', '/api/portal/auth/password-reset-requests', ['email' => $userEntity->getEmail()]);
        self::assertResponseStatusCodeSame(202);
        $knownResponse = $this->responseJson($client);

        $this->requestJson($client, 'POST', '/api/portal/auth/password-reset-requests', ['email' => 'unknown-' . bin2hex(random_bytes(8)) . '@portal.test']);
        self::assertResponseStatusCodeSame(202);

        self::assertSame($knownResponse, $this->responseJson($client));
    }

    public function testPasswordTokenConsumptionActivatesTheAccountRevokesPreviousSessionsAndLogsIn(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $userEntity = (new UserEntity())->setEmail('pending-' . bin2hex(random_bytes(8)) . '@portal.test');
        $organizationEntity = (new OrganizationEntity())->setName('Structure invitée');
        $userOrganizationAccessEntity = (new UserOrganizationAccessEntity())
            ->setOrganization($organizationEntity)
            ->setUser($userEntity);
        $userEntity->addOrganizationAccess($userOrganizationAccessEntity);
        $entityManager->persist($organizationEntity);
        $entityManager->persist($userEntity);
        $entityManager->persist($userOrganizationAccessEntity);
        $entityManager->flush();

        $portalPasswordTokenManager = static::getContainer()->get(PortalPasswordTokenManager::class);
        $issuedPortalPasswordToken = $portalPasswordTokenManager->issueInvitation($userEntity);
        $issuedPortalSession = static::getContainer()->get(PortalSessionManager::class)->create($userEntity);

        $this->requestJson($client, 'POST', '/api/portal/password-tokens/' . $issuedPortalPasswordToken->rawToken . '/consume', [
            'password' => 'Une phrase de passe solide',
        ]);

        self::assertResponseIsSuccessful();
        $response = $this->responseJson($client);
        self::assertIsString($response['token'] ?? null);
        $entityManager->clear();
        $reloadedUserEntity = $entityManager->find(UserEntity::class, $userEntity->getId());
        self::assertInstanceOf(UserEntity::class, $reloadedUserEntity);
        self::assertSame(UserStatus::ACTIVE, $reloadedUserEntity->getStatus());
        self::assertNull(static::getContainer()->get(PortalSessionManager::class)->findAuthenticatedUser($issuedPortalSession->rawToken));

        $client->request('GET', '/api/portal/me', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $response['token']]);
        self::assertResponseIsSuccessful();
    }

    public function testPrivateEndpointsRequireABearerSession(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/portal/sessions');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['message' => 'Authentication required.'], $this->responseJson($client));
    }

    public function testSessionsAreDeduplicatedSortedAndFilteredInSqlByAuthorizedOrganizations(): void
    {
        [$client, $userEntity, $firstOrganizationEntity] = $this->createActiveUserWithOrganization();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $secondOrganizationEntity = (new OrganizationEntity())->setName('Deuxième structure');
        $secondUserOrganizationAccessEntity = (new UserOrganizationAccessEntity())
            ->setUser($userEntity)
            ->setOrganization($secondOrganizationEntity);
        $entityManager->persist($secondOrganizationEntity);
        $entityManager->persist($secondUserOrganizationAccessEntity);

        $sharedSessionEntity = $this->createSessionSummary('Séance partagée', new DateTimeImmutable('2026-09-10'), [$firstOrganizationEntity, $secondOrganizationEntity]);
        $olderSessionEntity = $this->createSessionSummary('Séance ancienne', new DateTimeImmutable('2026-09-01'), [$firstOrganizationEntity]);
        $entityManager->persist($sharedSessionEntity);
        $entityManager->persist($olderSessionEntity);
        $entityManager->flush();
        $token = $this->login($client, $userEntity);

        $client->request('GET', '/api/portal/sessions?page=1', server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);

        self::assertResponseIsSuccessful();
        $response = $this->responseJson($client);
        self::assertSame(2, $response['pagination']['total']);
        self::assertSame([
            $olderSessionEntity->getSlug(),
            $sharedSessionEntity->getSlug(),
        ], array_column($response['items'], 'slug'));
        self::assertSame([
            ['uuid' => $secondOrganizationEntity->getUuid()->toRfc4122(), 'name' => $secondOrganizationEntity->getName()],
            ['uuid' => $firstOrganizationEntity->getUuid()->toRfc4122(), 'name' => $firstOrganizationEntity->getName()],
        ], $response['items'][1]['organizations']);

        $client->request('GET', '/api/portal/sessions?organization=' . $secondOrganizationEntity->getUuid()->toRfc4122(), server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);

        self::assertResponseIsSuccessful();
        self::assertSame([$sharedSessionEntity->getSlug()], array_column($this->responseJson($client)['items'], 'slug'));
    }

    public function testUnauthorizedSessionDetailAndDocumentAreIndistinguishableFromMissingResources(): void
    {
        [$client, $userEntity] = $this->createActiveUserWithOrganization();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $otherOrganizationEntity = (new OrganizationEntity())->setName('Structure interdite');
        $forbiddenSessionEntity = $this->createSessionSummary('Séance interdite', new DateTimeImmutable('2026-09-11'), [$otherOrganizationEntity]);
        $entityManager->persist($otherOrganizationEntity);
        $entityManager->persist($forbiddenSessionEntity);
        $entityManager->flush();
        $token = $this->login($client, $userEntity);

        foreach ([
            '/api/portal/sessions/' . $forbiddenSessionEntity->getSlug(),
            '/api/portal/sessions/' . $forbiddenSessionEntity->getSlug() . '/document.pdf',
        ] as $url) {
            $client->request('GET', $url, server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testAuthorizedSessionIsResolvedBySlug(): void
    {
        [$client, $userEntity, $organizationEntity] = $this->createActiveUserWithOrganization();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $sessionSlug = 'seance-automne-' . bin2hex(random_bytes(4));
        $sessionSummaryEntity = $this->createSessionSummary('Séance automne', new DateTimeImmutable('2026-09-11'), [$organizationEntity])
            ->setSlug($sessionSlug);
        $entityManager->persist($sessionSummaryEntity);
        $entityManager->flush();
        $token = $this->login($client, $userEntity);

        $client->request('GET', '/api/portal/sessions/' . $sessionSlug, server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);

        self::assertResponseIsSuccessful();
        self::assertSame($sessionSlug, $this->responseJson($client)['slug']);
    }

    public function testSessionResponsesExposeOnlyThePortalContract(): void
    {
        [$client, $userEntity, $organizationEntity] = $this->createActiveUserWithOrganization();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $instrumentEntity = (new InstrumentEntity())->setName('Anneaux en métal');
        $sessionSummaryEntity = $this->createSessionSummary('Séance portail', new DateTimeImmutable('2026-09-14'), [$organizationEntity])
            ->setTheme('Les sons de l’eau')
            ->setGeneralNotes('Une intention pédagogique.')
            ->setMaterialSummary('Des bols et des cuillères.')
            ->setFurtherExploration('Prolonger à la maison.')
            ->setInstrumentUuids([$instrumentEntity->getUuid()->toRfc4122()])
            ->setRecommendationUuids(['recommendation-uuid'])
            ->setSequences([['title' => 'Accueil']])
            ->setDocumentStatus(SessionDocumentStatus::READY);
        $entityManager->persist($instrumentEntity);
        $entityManager->persist($sessionSummaryEntity);
        $entityManager->flush();
        $token = $this->login($client, $userEntity);

        $client->request('GET', '/api/portal/sessions', server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);

        self::assertResponseIsSuccessful();
        $listItem = $this->responseJson($client)['items'][0];
        self::assertSame([
            'slug',
            'title',
            'sessionDate',
            'sharedAt',
            'organizations',
            'theme',
            'documentStatus',
        ], array_keys($listItem));
        self::assertSame(SessionDocumentStatus::READY->value, $listItem['documentStatus']);

        $client->request('GET', '/api/portal/sessions/' . $sessionSummaryEntity->getSlug(), server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);

        self::assertResponseIsSuccessful();
        $detail = $this->responseJson($client);
        self::assertSame([
            'slug',
            'title',
            'sessionDate',
            'sharedAt',
            'organizations',
            'theme',
            'documentStatus',
            'generalNotes',
            'materialSummary',
            'furtherExploration',
            'instrumentUuids',
            'instrumentNames',
            'recommendationUuids',
            'sequences',
        ], array_keys($detail));
        self::assertSame([$instrumentEntity->getUuid()->toRfc4122()], $detail['instrumentUuids']);
        self::assertSame(['Anneaux en métal'], $detail['instrumentNames']);
        self::assertSame(['recommendation-uuid'], $detail['recommendationUuids']);
    }

    public function testSessionDetailExposesYoutubeMediaLinkedFromItsRepertoireItem(): void
    {
        [$client, $userEntity, $organizationEntity] = $this->createActiveUserWithOrganization();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $youtubeMediaEntity = (new MediaResourceEntity())
            ->setTitle('La comptine en vidéo')
            ->setType(MediaResourceType::VIDEO)
            ->setPrimaryUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $repertoireItemEntity = (new RepertoireItemEntity())
            ->setTitle('La comptine')
            ->setType(RepertoireItemType::NURSERY_RHYME)
            ->setLinkedMediaUuids([$youtubeMediaEntity->getUuid()->toRfc4122()]);
        $sessionSummaryEntity = $this->createSessionSummary('Séance avec comptine', new DateTimeImmutable('2026-09-15'), [$organizationEntity])
            ->setSequences([[
                'uuid' => Uuid::v4()->toRfc4122(),
                'type' => SessionSequenceType::NURSERY_RHYME->value,
                'title' => 'La comptine',
                'subtitle' => null,
                'body' => '',
                'lyrics' => null,
                'gestures' => null,
                'notes' => null,
                'media' => [],
                'showLyricsByDefault' => false,
                'sourceUuid' => $repertoireItemEntity->getUuid()->toRfc4122(),
                'sourceKind' => SessionSequenceSourceKind::REPERTOIRE_ITEM->value,
                'sourceTitle' => 'La comptine',
            ]]);
        $entityManager->persist($youtubeMediaEntity);
        $entityManager->persist($repertoireItemEntity);
        $entityManager->persist($sessionSummaryEntity);
        $entityManager->flush();
        $token = $this->login($client, $userEntity);

        $client->request('GET', '/api/portal/sessions/' . $sessionSummaryEntity->getSlug(), server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);

        self::assertResponseIsSuccessful();
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $this->responseJson($client)['sequences'][0]['documentMedia'][0]['url']);
    }

    public function testReadyDocumentStreamsPdfAndPendingDocumentReturnsNotFound(): void
    {
        [$client, $userEntity, $organizationEntity] = $this->createActiveUserWithOrganization();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $readySessionEntity = $this->createSessionSummary('Séance prête', new DateTimeImmutable('2026-09-12'), [$organizationEntity]);
        $readySessionEntity->setDocumentStatus(SessionDocumentStatus::READY);
        $pendingSessionEntity = $this->createSessionSummary('Séance en préparation', new DateTimeImmutable('2026-09-13'), [$organizationEntity]);
        $entityManager->persist($readySessionEntity);
        $entityManager->persist($pendingSessionEntity);
        $entityManager->flush();
        $entityManager->clear();
        $reloadedReadySessionEntity = $entityManager->getRepository(SessionSummaryEntity::class)->findOneBy(['uuid' => $readySessionEntity->getUuid()]);
        self::assertInstanceOf(SessionSummaryEntity::class, $reloadedReadySessionEntity);
        self::assertSame(SessionDocumentStatus::READY, $reloadedReadySessionEntity->getDocumentStatus());
        $documentPath = dirname(__DIR__, 4) . '/var/session-documents/' . $readySessionEntity->getUuid()->toRfc4122() . '.pdf';
        if (!is_dir(dirname($documentPath))) {
            mkdir(dirname($documentPath), 0775, true);
        }
        file_put_contents($documentPath, '%PDF-1.4 test document');
        $token = $this->login($client, $userEntity);

        try {
            $client->request('GET', '/api/portal/sessions', server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);
            self::assertResponseIsSuccessful();
            self::assertContains($readySessionEntity->getSlug(), array_column($this->responseJson($client)['items'], 'slug'));

            $client->request('GET', '/api/portal/sessions/' . $readySessionEntity->getSlug(), server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);
            self::assertResponseIsSuccessful();
            self::assertSame(SessionDocumentStatus::READY->value, $this->responseJson($client)['documentStatus']);

            $client->request('GET', '/api/portal/sessions/' . $readySessionEntity->getSlug() . '/document.pdf', server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);
            self::assertResponseIsSuccessful();
            self::assertSame('application/pdf', $client->getResponse()->headers->get('Content-Type'));
            self::assertStringContainsString('attachment;', (string) $client->getResponse()->headers->get('Content-Disposition'));

            $client->request('GET', '/api/portal/sessions/' . $pendingSessionEntity->getSlug() . '/document.pdf', server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);
            self::assertResponseStatusCodeSame(404);
        } finally {
            @unlink($documentPath);
        }
    }

    /** @return array{0: KernelBrowser, 1: UserEntity, 2: OrganizationEntity} */
    private function createActiveUserWithOrganization(): array
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $userEntity = (new UserEntity())
            ->setEmail('portal-' . bin2hex(random_bytes(8)) . '@portal.test')
            ->setStatus(UserStatus::ACTIVE);
        $userEntity->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($userEntity, 'Une phrase de passe solide'));
        $organizationEntity = (new OrganizationEntity())->setName('Structure autorisée');
        $userOrganizationAccessEntity = (new UserOrganizationAccessEntity())
            ->setUser($userEntity)
            ->setOrganization($organizationEntity);
        $userEntity->addOrganizationAccess($userOrganizationAccessEntity);
        $entityManager->persist($organizationEntity);
        $entityManager->persist($userEntity);
        $entityManager->persist($userOrganizationAccessEntity);
        $entityManager->flush();

        return [$client, $userEntity, $organizationEntity];
    }

    /** @param list<OrganizationEntity> $organizationEntities */
    private function createSessionSummary(string $title, DateTimeImmutable $sessionDate, array $organizationEntities): SessionSummaryEntity
    {
        return (new SessionSummaryEntity())
            ->setTitle($title)
            ->setSlug('session-' . Uuid::v4()->toRfc4122())
            ->setSessionDate($sessionDate)
            ->replaceOrganizations($organizationEntities);
    }

    private function login(KernelBrowser $client, UserEntity $userEntity): string
    {
        $this->requestJson($client, 'POST', '/api/portal/auth/login', [
            'email' => $userEntity->getEmail(),
            'password' => 'Une phrase de passe solide',
        ]);
        self::assertResponseIsSuccessful();

        return $this->responseJson($client)['token'];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $server
     */
    private function requestJson(KernelBrowser $client, string $method, string $uri, array $payload, array $server = []): void
    {
        $client->request($method, $uri, server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.' . random_int(1, 254),
            ...$server,
        ], content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function responseJson(KernelBrowser $client): array
    {
        $response = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($response);

        return $response;
    }
}
