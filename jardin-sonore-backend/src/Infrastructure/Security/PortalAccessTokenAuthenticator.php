<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Portal\PortalSessionManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class PortalAccessTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly PortalSessionManager $portalSessionManager)
    {
    }

    public function supports(Request $request): bool
    {
        return null !== $this->extractRawToken($request);
    }

    public function authenticate(Request $request): Passport
    {
        $rawToken = $this->extractRawToken($request);
        if (null === $rawToken) {
            throw new CustomUserMessageAuthenticationException('Authentication required.');
        }

        return new SelfValidatingPassport(new UserBadge($rawToken, function (string $rawToken) {
            $userEntity = $this->portalSessionManager->findAuthenticatedUser($rawToken);
            if (null === $userEntity) {
                throw new CustomUserMessageAuthenticationException('Authentication required.');
            }

            return $userEntity;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
    }

    private function extractRawToken(Request $request): ?string
    {
        $authorizationHeader = $request->headers->get('Authorization');
        if (!is_string($authorizationHeader) || 1 !== preg_match('/^Bearer\\s+([^\\s]+)$/i', $authorizationHeader, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
