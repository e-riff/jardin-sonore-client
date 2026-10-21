<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\CreateStandaloneMailDraft;
use App\Application\Mcp\MailDraftStore;
use App\Infrastructure\Mail\StandaloneDraftMimeBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CreateStandaloneMailDraftTest extends TestCase
{
    public function testCreatesVerifiedStandaloneDraftWithoutReplyHeaders(): void
    {
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->expects(self::exactly(2))->method('findByMessageId')->willReturnOnConsecutiveCalls(null, 42);
        $draftStore->expects(self::once())->method('append')->with('INBOX.Drafts', self::callback(static function (string $mime): bool {
            return str_contains($mime, 'To: marie@example.test')
                && str_contains($mime, 'Bonjour Marie')
                && !str_contains($mime, 'In-Reply-To:');
        }));

        $result = (new CreateStandaloneMailDraft($draftStore, new StandaloneDraftMimeBuilder(), 'contact@example.test', 'INBOX.Drafts'))
            ->create('marie@example.test', 'Premier échange', 'Bonjour Marie');

        self::assertSame('saved', $result['status']);
        self::assertSame('marie@example.test', $result['to']);
        self::assertSame('Premier échange', $result['subject']);
        self::assertSame(42, $result['uid']);
    }

    public function testCreatesMultipartStandaloneDraftWhenHtmlIsProvided(): void
    {
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->expects(self::exactly(2))->method('findByMessageId')->willReturnOnConsecutiveCalls(null, 42);
        $draftStore->expects(self::once())->method('append')->with('INBOX.Drafts', self::stringContains('multipart/alternative'));

        $result = (new CreateStandaloneMailDraft($draftStore, new StandaloneDraftMimeBuilder(), 'contact@example.test', 'INBOX.Drafts'))
            ->create('marie@example.test', 'Premier échange', 'Bonjour Marie', '<p>Bonjour <strong>Marie</strong></p>');

        self::assertSame('saved', $result['status']);
        self::assertSame(42, $result['uid']);
    }

    public function testIdenticalRetryReturnsExistingDraftWithoutAppendingAgain(): void
    {
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->expects(self::once())->method('findByMessageId')->willReturn(42);
        $draftStore->expects(self::never())->method('append');

        $result = (new CreateStandaloneMailDraft($draftStore, new StandaloneDraftMimeBuilder(), 'contact@example.test', 'INBOX.Drafts'))
            ->create('marie@example.test', 'Premier échange', 'Bonjour Marie');

        self::assertSame('already_exists', $result['status']);
        self::assertSame(42, $result['uid']);
    }

    public function testAppendFailureReturnsUncertainAndDoesNotRetryInTheSameCall(): void
    {
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->expects(self::once())->method('findByMessageId')->willReturn(null);
        $draftStore->expects(self::once())->method('append')->willThrowException(new RuntimeException('connection lost'));

        $result = (new CreateStandaloneMailDraft($draftStore, new StandaloneDraftMimeBuilder(), 'contact@example.test', 'INBOX.Drafts'))
            ->create('marie@example.test', 'Premier échange', 'Bonjour Marie');

        self::assertSame('uncertain', $result['status']);
        self::assertArrayHasKey('messageId', $result);
    }

    public function testRejectsInvalidRecipientBeforeAccessingImap(): void
    {
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->expects(self::never())->method('findByMessageId');
        $draftStore->expects(self::never())->method('append');

        $this->expectException(InvalidArgumentException::class);
        (new CreateStandaloneMailDraft($draftStore, new StandaloneDraftMimeBuilder(), 'contact@example.test', 'INBOX.Drafts'))
            ->create('not-an-email', 'Premier échange', 'Bonjour Marie');
    }
}
