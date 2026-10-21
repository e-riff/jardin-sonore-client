<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Infrastructure\Mail\StandaloneDraftMimeBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class StandaloneDraftMimeBuilderTest extends TestCase
{
    public function testHtmlDraftIncludesTextAlternativeAndSanitizedHtml(): void
    {
        $mime = (new StandaloneDraftMimeBuilder())->build('marie@example.test', 'Bonjour', 'Bonjour Marie', '<mcp-test@example.test>', 'contact@example.test', new DateTimeImmutable('2026-10-21'), '<p>Bonjour <strong>Marie</strong></p><script>alert(1)</script>');

        self::assertStringContainsString('multipart/alternative', $mime);
        self::assertStringContainsString('text/plain', $mime);
        self::assertStringContainsString('text/html', $mime);
        self::assertStringNotContainsString('script', $mime);
    }
}
