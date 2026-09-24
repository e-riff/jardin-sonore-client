<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Application\Portal\PortalPasswordTokenManager;
use App\Application\Portal\PortalSessionAccessService;
use App\Application\Portal\PortalSessionManager;
use App\Application\Portal\PortalSessionReader;
use App\Application\Portal\PortalSessionResponse;
use App\Application\Storage\PortalAvatarStorageInterface;
use App\Domain\Model\Portal\UserStatus;
use App\Domain\Model\Session\SessionDocumentStatus;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Security\PortalRateLimitKeyResolver;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/portal')]
final class PortalApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PortalSessionManager $portalSessionManager,
        private readonly PortalPasswordTokenManager $portalPasswordTokenManager,
        private readonly PortalAccountMailSenderInterface $portalAccountMailSender,
        private readonly PortalSessionReader $portalSessionReader,
        private readonly PortalSessionAccessService $portalSessionAccessService,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        #[Autowire(service: 'limiter.portal_login')]
        private readonly RateLimiterFactoryInterface $portalLoginLimiter,
        #[Autowire(service: 'limiter.portal_password_reset')]
        private readonly RateLimiterFactoryInterface $portalPasswordResetLimiter,
        private readonly PortalRateLimitKeyResolver $portalRateLimitKeyResolver,
    ) {
    }

    #[Route('/auth/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        if (!$this->consumeLimiter($this->portalLoginLimiter, $request)) {
            return new JsonResponse(['message' => 'Too many requests.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = $this->jsonPayload($request);
        $email = isset($payload['email']) && is_string($payload['email']) ? mb_strtolower(trim($payload['email'])) : '';
        $password = isset($payload['password']) && is_string($payload['password']) ? $payload['password'] : '';
        $userEntity = '' === $email ? null : $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

        if (!$userEntity instanceof UserEntity
            || UserStatus::ACTIVE !== $userEntity->getStatus()
            || !$this->portalSessionReader->hasActiveOrganizationAccess($userEntity)
            || !$this->userPasswordHasher->isPasswordValid($userEntity, $password)) {
            return new JsonResponse(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse(['token' => $this->portalSessionManager->create($userEntity)->rawToken]);
    }

    #[Route('/auth/logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $rawToken = $this->rawBearerToken($request);
        if (null !== $rawToken) {
            $this->portalSessionManager->revoke($rawToken);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/auth/password-reset-requests', methods: ['POST'])]
    public function requestPasswordReset(Request $request): JsonResponse
    {
        if (!$this->consumeLimiter($this->portalPasswordResetLimiter, $request)) {
            return new JsonResponse(['message' => 'Too many requests.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = $this->jsonPayload($request);
        $email = isset($payload['email']) && is_string($payload['email']) ? mb_strtolower(trim($payload['email'])) : '';
        $userEntity = '' === $email ? null : $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

        if ($userEntity instanceof UserEntity
            && UserStatus::ACTIVE === $userEntity->getStatus()
            && $this->portalSessionReader->hasActiveOrganizationAccess($userEntity)) {
            $issuedPortalPasswordToken = $this->portalPasswordTokenManager->issuePasswordReset($userEntity);
            $this->portalAccountMailSender->sendPasswordReset($userEntity, $issuedPortalPasswordToken->rawToken);
        }

        return new JsonResponse(['message' => 'If this address is associated with an account, a reset link has been sent.'], Response::HTTP_ACCEPTED);
    }

    #[Route('/password-tokens/{token}/consume', methods: ['POST'])]
    public function consumePasswordToken(string $token, Request $request): JsonResponse
    {
        $userPasswordTokenEntity = $this->portalPasswordTokenManager->findUsable($token);
        $payload = $this->jsonPayload($request);
        $password = isset($payload['password']) && is_string($payload['password']) ? $payload['password'] : '';
        if (null === $userPasswordTokenEntity || 12 > mb_strlen($password)) {
            return new JsonResponse(['message' => 'This password link is unavailable.'], Response::HTTP_NOT_FOUND);
        }

        $userEntity = $userPasswordTokenEntity->getUser();
        $this->portalPasswordTokenManager->consumeWithPassword($userPasswordTokenEntity, $password);
        $this->portalSessionManager->revokeForUser($userEntity);

        return new JsonResponse(['token' => $this->portalSessionManager->create($userEntity)->rawToken]);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $userEntity = $this->portalUser();

        return new JsonResponse([
            'email' => $userEntity->getEmail(),
            'firstName' => $userEntity->getFirstName(),
            'lastName' => $userEntity->getLastName(),
            'avatarPath' => $userEntity->getAvatarPath(),
            'newSessionNotificationsEnabled' => $userEntity->isNewSessionNotificationsEnabled(),
            'organizations' => array_map(static fn ($organizationEntity): array => [
                'uuid' => $organizationEntity->getUuid()->toRfc4122(),
                'name' => $organizationEntity->getName(),
            ], $this->portalSessionReader->authorizedOrganizations($userEntity)),
        ]);
    }

    #[Route('/me/profile', methods: ['PATCH'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $firstName = isset($payload['firstName']) && is_string($payload['firstName']) ? trim($payload['firstName']) : null;
        $lastName = isset($payload['lastName']) && is_string($payload['lastName']) ? trim($payload['lastName']) : null;
        $newSessionNotificationsEnabled = isset($payload['newSessionNotificationsEnabled']) && is_bool($payload['newSessionNotificationsEnabled'])
            ? $payload['newSessionNotificationsEnabled']
            : null;
        if ((null !== $firstName && 100 < mb_strlen($firstName)) || (null !== $lastName && 100 < mb_strlen($lastName))) {
            return new JsonResponse(['message' => 'Profile fields are too long.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $userEntity = $this->portalUser();
        $userEntity->setFirstName($firstName)->setLastName($lastName);
        if (null !== $newSessionNotificationsEnabled) {
            $userEntity->setNewSessionNotificationsEnabled($newSessionNotificationsEnabled);
        }
        $this->entityManager->flush();

        return $this->me();
    }

    #[Route('/me/avatar', methods: ['POST'])]
    public function updateAvatar(Request $request, PortalAvatarStorageInterface $portalAvatarStorage): JsonResponse
    {
        $uploadedFile = $request->files->get('avatar');
        if (!$uploadedFile instanceof UploadedFile
            || !$uploadedFile->isValid()
            || 2_000_000 < $uploadedFile->getSize()
            || !in_array($uploadedFile->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return new JsonResponse(['message' => 'Please upload a JPEG, PNG or WebP image under 2 MB.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $userEntity = $this->portalUser();
        $userEntity->setAvatarPath($portalAvatarStorage->store($uploadedFile));
        $this->entityManager->flush();

        return $this->me();
    }

    #[Route('/me/avatar', methods: ['GET'])]
    public function avatar(): Response
    {
        $avatarPath = $this->portalUser()->getAvatarPath();
        if (null === $avatarPath) {
            throw $this->createNotFoundException();
        }

        $avatarFilePath = $this->getParameter('kernel.project_dir') . '/public/uploads/portal/avatars/' . basename($avatarPath);
        if (!is_file($avatarFilePath)) {
            throw $this->createNotFoundException();
        }

        return (new BinaryFileResponse($avatarFilePath))
            ->setPrivate()
            ->setMaxAge(0);
    }

    #[Route('/sessions', methods: ['GET'])]
    public function sessions(Request $request): JsonResponse
    {
        $userEntity = $this->portalUser();
        $paginatedSessions = $this->portalSessionReader->paginated(
            $userEntity,
            $request->query->getString('organization') ?: null,
            max(1, $request->query->getInt('page', 1)),
        );
        $authorizedOrganizationEntities = $this->portalSessionReader->authorizedOrganizations($userEntity);

        return new JsonResponse([
            'items' => array_map(
                static fn ($sessionSummaryEntity): array => PortalSessionResponse::fromEntity($sessionSummaryEntity, $authorizedOrganizationEntities)->toArray(false),
                $paginatedSessions['items'],
            ),
            'pagination' => [
                'page' => $paginatedSessions['page'],
                'pageSize' => $paginatedSessions['pageSize'],
                'total' => $paginatedSessions['total'],
            ],
        ]);
    }

    #[Route('/sessions/{slug}', methods: ['GET'])]
    public function detail(string $slug): JsonResponse
    {
        $userEntity = $this->portalUser();
        $sessionSummaryEntity = $this->portalSessionAccessService->findAuthorizedSession($userEntity, $slug);
        if (null === $sessionSummaryEntity) {
            throw $this->createNotFoundException();
        }

        return new JsonResponse(PortalSessionResponse::fromEntity(
            $sessionSummaryEntity,
            $this->portalSessionReader->authorizedOrganizations($userEntity),
            $this->portalSessionReader->detailSequences($sessionSummaryEntity),
            $this->portalSessionReader->instrumentNames($sessionSummaryEntity),
        )->toArray());
    }

    #[Route('/sessions/{slug}/document.pdf', methods: ['GET'])]
    public function document(
        string $slug,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/var/session-documents')]
        string $sessionDocumentDirectory,
    ): Response {
        $sessionSummaryEntity = $this->portalSessionAccessService->findAuthorizedSession($this->portalUser(), $slug);
        if (null === $sessionSummaryEntity || SessionDocumentStatus::READY !== $sessionSummaryEntity->getDocumentStatus()) {
            throw $this->createNotFoundException();
        }

        $documentPath = $sessionDocumentDirectory . '/' . $sessionSummaryEntity->getUuid()->toRfc4122() . '.pdf';
        clearstatcache(true, $documentPath);
        if (!is_file($documentPath)) {
            throw $this->createNotFoundException();
        }
        $sessionTitleSlug = $slugger->slug($sessionSummaryEntity->getTitle())->lower()->toString();
        $documentFileName = '' === $sessionTitleSlug ? 'seance.pdf' : "seance-{$sessionTitleSlug}.pdf";

        return (new BinaryFileResponse($documentPath))
            ->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $documentFileName);
    }

    private function portalUser(): UserEntity
    {
        $userEntity = $this->getUser();
        if (!$userEntity instanceof UserEntity) {
            throw $this->createAccessDeniedException();
        }

        return $userEntity;
    }

    /** @return array<string, mixed> */
    private function jsonPayload(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function consumeLimiter(RateLimiterFactoryInterface $rateLimiterFactory, Request $request): bool
    {
        return $rateLimiterFactory->create($this->portalRateLimitKeyResolver->resolve($request))->consume()->isAccepted();
    }

    private function rawBearerToken(Request $request): ?string
    {
        $authorizationHeader = $request->headers->get('Authorization');

        return is_string($authorizationHeader) && 1 === preg_match('/^Bearer\\s+([^\\s]+)$/i', $authorizationHeader, $matches)
            ? $matches[1]
            : null;
    }
}
