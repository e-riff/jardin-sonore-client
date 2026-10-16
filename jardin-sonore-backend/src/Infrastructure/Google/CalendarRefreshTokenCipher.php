<?php

declare(strict_types=1);

namespace App\Infrastructure\Google;

use DomainException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class CalendarRefreshTokenCipher
{
    public function __construct(#[Autowire('%app.commercial.calendar_encryption_key%')] private string $encodedKey)
    {
    }

    public function isConfigured(): bool
    {
        $key = base64_decode($this->encodedKey, true);

        return false !== $key && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen($key);
    }

    public function encrypt(string $refreshToken): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($refreshToken, $nonce, $this->key()));
    }

    public function decrypt(string $encryptedToken): string
    {
        $payload = base64_decode($encryptedToken, true);
        if (false === $payload || SODIUM_CRYPTO_SECRETBOX_NONCEBYTES >= strlen($payload)) {
            throw new DomainException('The stored calendar token is invalid.');
        }

        $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $refreshToken = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key());
        if (false === $refreshToken) {
            throw new DomainException('The stored calendar token cannot be decrypted.');
        }

        return $refreshToken;
    }

    private function key(): string
    {
        $key = base64_decode($this->encodedKey, true);
        if (false === $key || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($key)) {
            throw new DomainException('The calendar encryption key is invalid.');
        }

        return $key;
    }
}
