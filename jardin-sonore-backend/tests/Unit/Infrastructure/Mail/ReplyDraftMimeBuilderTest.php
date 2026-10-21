<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Application\Mcp\ReplyContext;
use App\Infrastructure\Mail\ReplyDraftMimeBuilder;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReplyDraftMimeBuilderTest extends TestCase
{
    public function testBuildsAThreadedUtf8PlainTextReply(): void
    {
        $context = new ReplyContext('source-key', '<source@example.test>', 'Éveil musical', 'marie@example.test', '<parent@example.test>', 'reference');
        $message = (new ReplyDraftMimeBuilder())->build($context, "Bonjour Marie,\nÀ mardi !", '<draft@example.test>', 'contact@example.test', new DateTimeImmutable('2026-10-19T12:00:00+02:00'));

        self::assertStringContainsString('From: contact@example.test', $message);
        self::assertStringContainsString('To: marie@example.test', $message);
        self::assertStringContainsString('In-Reply-To: <source@example.test>', $message);
        self::assertStringContainsString('References: <parent@example.test> <source@example.test>', $message);
        self::assertStringContainsString('content-type: text/plain; charset=utf-8', strtolower($message));
        self::assertStringContainsString('Bonjour Marie,', $message);
    }

    public function testDoesNotDuplicateReplyPrefixAndRejectsHeaderInjection(): void
    {
        $context = new ReplyContext('source-key', null, 'Re: Déjà répondu', 'marie@example.test', null, 'reference');
        $builder = new ReplyDraftMimeBuilder();
        $message = $builder->build($context, 'Merci', '<draft@example.test>', 'contact@example.test', new DateTimeImmutable());
        self::assertStringNotContainsString('Re: Re:', $message);

        $this->expectException(InvalidArgumentException::class);
        $builder->build(new ReplyContext('source-key', null, "Sujet\r\nBcc: evil@example.test", 'marie@example.test', null, 'reference'), 'Merci', '<draft@example.test>', 'contact@example.test', new DateTimeImmutable());
    }

    public function testBuildsAReplyWithTextAndSafeHtmlAlternatives(): void
    {
        $context = new ReplyContext('source-key', '<source@example.test>', 'Bonjour', 'marie@example.test', null, 'reference');
        $message = (new ReplyDraftMimeBuilder())->build($context, 'Merci Marie', '<draft@example.test>', 'contact@example.test', new DateTimeImmutable(), '<p>Merci <strong>Marie</strong></p><script>bad()</script>');

        self::assertStringContainsString('multipart/alternative', $message);
        self::assertStringContainsString('text/plain', $message);
        self::assertStringContainsString('text/html', $message);
        self::assertStringContainsString('<p>Merci <strong>Marie</strong></p>', $message);
        self::assertStringNotContainsString('bad()', $message);
    }
}
