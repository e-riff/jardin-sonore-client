<?php

declare(strict_types=1);

namespace App\Infrastructure\Google;

use App\Application\Commercial\GoogleCalendarConnectionStoreInterface;
use App\Infrastructure\Doctrine\Entity\GoogleCalendarConnectionEntity;
use DomainException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GoogleCalendarOAuthClient
{
    private const string SCOPE_EVENTS = 'https://www.googleapis.com/auth/calendar.events.owned';
    private const string SCOPE_EMAIL = 'https://www.googleapis.com/auth/userinfo.email';
    private const string TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const string USER_INFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CalendarRefreshTokenCipher $cipher,
        private GoogleCalendarConnectionStoreInterface $connectionStore,
        private ClockInterface $clock,
        #[Autowire('%app.commercial.calendar_client_id%')] private string $clientId,
        #[Autowire('%app.commercial.calendar_client_secret%')] private string $clientSecret,
        #[Autowire('%app.commercial.calendar_redirect_uri%')] private string $redirectUri,
    ) {
    }

    public function authorizationUrl(string $state): string
    {
        $this->assertConfigured();

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', ['openid', self::SCOPE_EMAIL, self::SCOPE_EVENTS]),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function isConfigured(): bool
    {
        return '' !== $this->clientId && '' !== $this->clientSecret && '' !== $this->redirectUri && $this->cipher->isConfigured();
    }

    public function connectWithCode(string $code): GoogleCalendarConnectionEntity
    {
        $this->assertConfigured();
        $tokenResponse = $this->postToken([
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ]);
        $accessToken = $this->requiredString($tokenResponse, 'access_token');
        $grantedScopes = preg_split('/\s+/', (string) ($tokenResponse['scope'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!in_array(self::SCOPE_EVENTS, $grantedScopes, true) || !in_array(self::SCOPE_EMAIL, $grantedScopes, true)) {
            throw new GoogleCalendarOAuthException('Google did not grant the required Calendar and account scopes.');
        }
        $existingConnection = $this->connectionStore->get();
        $refreshToken = $tokenResponse['refresh_token'] ?? null;
        if (!is_string($refreshToken) || '' === $refreshToken) {
            if (null === $existingConnection) {
                throw new GoogleCalendarOAuthException('Google did not provide an offline refresh token. Reconnect and grant offline access.');
            }
            $refreshToken = $this->decryptStoredToken($existingConnection);
        }

        $email = $this->readAccountEmail($accessToken);
        $now = $this->clock->now();
        if (null === $existingConnection) {
            $connectionEntity = new GoogleCalendarConnectionEntity($this->cipher->encrypt($refreshToken), $email, $now);
        } else {
            $existingConnection->reconnect($this->cipher->encrypt($refreshToken), $email, $now);
            $connectionEntity = $existingConnection;
        }

        $this->connectionStore->save($connectionEntity);

        return $connectionEntity;
    }

    public function getAccessToken(): string
    {
        $this->assertConfigured();
        $connectionEntity = $this->connectionStore->get();
        if (null === $connectionEntity) {
            throw new GoogleCalendarOAuthException('Google Calendar is not connected.');
        }

        $tokenResponse = $this->postToken([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->decryptStoredToken($connectionEntity),
            'grant_type' => 'refresh_token',
        ]);

        return $this->requiredString($tokenResponse, 'access_token');
    }

    public function connection(): ?GoogleCalendarConnectionEntity
    {
        return $this->connectionStore->get();
    }

    public function disconnect(): bool
    {
        $connectionEntity = $this->connectionStore->get();
        if (null === $connectionEntity) {
            return true;
        }

        $revoked = false;
        try {
            $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/revoke', [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => ['token' => $this->decryptStoredToken($connectionEntity)],
            ]);
            $revoked = 200 === $response->getStatusCode();
        } catch (GoogleCalendarOAuthException|TransportExceptionInterface) {
            $revoked = false;
        } finally {
            $this->connectionStore->delete();
        }

        return $revoked;
    }

    public function markAccessSucceeded(): void
    {
        $connectionEntity = $this->connectionStore->get();
        if (null !== $connectionEntity) {
            $connectionEntity->markAccessSucceeded($this->clock->now());
            $this->connectionStore->save($connectionEntity);
        }
    }

    /** @param array<string, string> $parameters
     * @return array<string, mixed>
     */
    private function postToken(array $parameters): array
    {
        try {
            $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => $parameters,
            ]);
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new GoogleCalendarOAuthException('Google Calendar could not be reached.', previous: $exception);
        }

        if (200 !== $response->getStatusCode() || isset($data['error'])) {
            throw new GoogleCalendarOAuthException('Google rejected the Calendar authorization request.');
        }

        return $data;
    }

    private function readAccountEmail(string $accessToken): string
    {
        try {
            $response = $this->httpClient->request('GET', self::USER_INFO_URL, ['auth_bearer' => $accessToken]);
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new GoogleCalendarOAuthException('The Google account could not be verified.', previous: $exception);
        }

        if (200 !== $response->getStatusCode() || true !== ($data['email_verified'] ?? false)) {
            throw new GoogleCalendarOAuthException('Google did not return a verified account email.');
        }

        return $this->requiredString($data, 'email');
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || '' === $value) {
            throw new GoogleCalendarOAuthException("Google did not return {$key}.");
        }

        return $value;
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new GoogleCalendarOAuthException('Google Calendar OAuth is not configured.');
        }
    }

    private function decryptStoredToken(GoogleCalendarConnectionEntity $connectionEntity): string
    {
        try {
            return $this->cipher->decrypt($connectionEntity->getEncryptedRefreshToken());
        } catch (DomainException $exception) {
            throw new GoogleCalendarOAuthException('The saved Google Calendar connection cannot be decrypted.', previous: $exception);
        }
    }
}
