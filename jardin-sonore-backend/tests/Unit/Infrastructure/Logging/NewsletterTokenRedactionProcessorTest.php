<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Logging;

use App\Infrastructure\Logging\NewsletterTokenRedactionProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class NewsletterTokenRedactionProcessorTest extends TestCase
{
    public function testRouteAndExceptionContextCannotLogConfirmationTokens(): void
    {
        $token = str_repeat('a', 64);
        $record = new LogRecord(new DateTimeImmutable(), 'request', Level::Info, 'POST /api/newsletter/confirmations/' . $token, [
            'route_parameters' => ['token' => $token, '_route' => 'newsletter_api_confirm'],
            'request_uri' => 'https://www.example.test/newsletter/confirmer/' . $token,
        ]);
        $redactedRecord = (new NewsletterTokenRedactionProcessor())($record);
        self::assertStringNotContainsString($token, $redactedRecord->message);
        self::assertSame('[redacted]', $redactedRecord->context['route_parameters']['token']);
        self::assertSame('https://www.example.test/newsletter/confirmer/[redacted]', $redactedRecord->context['request_uri']);
    }
}
