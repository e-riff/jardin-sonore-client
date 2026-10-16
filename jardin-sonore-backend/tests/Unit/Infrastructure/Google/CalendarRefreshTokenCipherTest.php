<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Google;

use App\Infrastructure\Google\CalendarRefreshTokenCipher;
use DomainException;
use PHPUnit\Framework\TestCase;

final class CalendarRefreshTokenCipherTest extends TestCase
{
    public function testTokenIsEncryptedWithAUniqueNonceAndCanBeRecovered(): void
    {
        $cipher = new CalendarRefreshTokenCipher(base64_encode(str_repeat('a', 32)));

        $first = $cipher->encrypt('refresh-token');
        $second = $cipher->encrypt('refresh-token');

        self::assertNotSame($first, $second);
        self::assertStringNotContainsString('refresh-token', $first);
        self::assertSame('refresh-token', $cipher->decrypt($first));
        self::assertSame('refresh-token', $cipher->decrypt($second));
    }

    public function testWrongKeyCannotReadStoredToken(): void
    {
        $firstCipher = new CalendarRefreshTokenCipher(base64_encode(str_repeat('a', 32)));
        $secondCipher = new CalendarRefreshTokenCipher(base64_encode(str_repeat('b', 32)));

        $this->expectException(DomainException::class);
        $secondCipher->decrypt($firstCipher->encrypt('refresh-token'));
    }

    public function testInvalidKeyIsRejected(): void
    {
        $cipher = new CalendarRefreshTokenCipher('too-short');
        self::assertFalse($cipher->isConfigured());

        $this->expectException(DomainException::class);
        $cipher->encrypt('refresh-token');
    }
}
