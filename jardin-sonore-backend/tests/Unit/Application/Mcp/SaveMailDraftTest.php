<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\DraftReceipt;
use App\Application\Mcp\MailDraftReceiptStore;
use App\Application\Mcp\MailDraftStore;
use App\Application\Mcp\MailReader;
use App\Application\Mcp\ReplyContext;
use App\Application\Mcp\SaveMailDraft;
use App\Infrastructure\Mail\ReplyDraftMimeBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SaveMailDraftTest extends TestCase
{
    public function testNewDraftIsAppendedAndRecordedOnce(): void
    {
        $sourceKey = str_repeat('d', 64);
        $draftMessageId = "<mcp-draft-{$sourceKey}@example.test>";
        $reader = $this->createStub(MailReader::class);
        $reader->method('replyContext')->willReturn(new ReplyContext($sourceKey, '<source@example.test>', 'Bonjour', 'marie@example.test', null, 'reference'));
        $receipts = $this->createMock(MailDraftReceiptStore::class);
        $receipts->expects(self::once())->method('reserve')->with($sourceKey, $draftMessageId, 'Drafts')
            ->willReturn(new DraftReceipt($sourceKey, $draftMessageId, 'Drafts', 'reserved', true, null));
        $receipts->expects(self::once())->method('markSaved')->with($sourceKey, 54);
        $drafts = $this->createMock(MailDraftStore::class);
        $drafts->expects(self::once())->method('append')->with('Drafts', self::stringContains('Bonjour Marie'));
        $drafts->expects(self::once())->method('findByMessageId')->with('Drafts', $draftMessageId)->willReturn(54);

        $result = (new SaveMailDraft($reader, $receipts, $drafts, new ReplyDraftMimeBuilder(), 'contact@example.test', 'Drafts'))->save('reference', 'Bonjour Marie');

        self::assertSame(['status' => 'saved', 'folder' => 'Drafts', 'messageId' => $draftMessageId, 'uid' => 54], $result);
    }

    public function testARepeatReturnsTheExistingDraftWithoutAppendingAgain(): void
    {
        $reader = $this->createStub(MailReader::class);
        $reader->method('replyContext')->willReturn(new ReplyContext(str_repeat('a', 64), '<source@example.test>', 'Bonjour', 'marie@example.test', null, 'reference'));
        $receipts = $this->createMock(MailDraftReceiptStore::class);
        $receipts->expects(self::once())->method('reserve')->willReturn(new DraftReceipt(str_repeat('a', 64), '<draft@example.test>', 'Drafts', 'saved', false, 42));
        $drafts = $this->createMock(MailDraftStore::class);
        $drafts->expects(self::once())->method('findByMessageId')->with('Drafts', '<draft@example.test>')->willReturn(42);
        $drafts->expects(self::never())->method('append');

        $result = (new SaveMailDraft($reader, $receipts, $drafts, new ReplyDraftMimeBuilder(), 'contact@example.test', 'Drafts'))->save('reference', 'Nouvelle proposition');

        self::assertSame('already_exists', $result['status']);
        self::assertSame(42, $result['uid']);
    }

    public function testAnUncertainAppendNeverTriggersASecondAppend(): void
    {
        $sourceKey = str_repeat('b', 64);
        $reader = $this->createStub(MailReader::class);
        $reader->method('replyContext')->willReturn(new ReplyContext($sourceKey, '<source@example.test>', 'Bonjour', 'marie@example.test', null, 'reference'));
        $receipts = $this->createMock(MailDraftReceiptStore::class);
        $receipts->expects(self::exactly(2))->method('reserve')->willReturnOnConsecutiveCalls(
            new DraftReceipt($sourceKey, "<mcp-draft-{$sourceKey}@example.test>", 'Drafts', 'reserved', true, null),
            new DraftReceipt($sourceKey, "<mcp-draft-{$sourceKey}@example.test>", 'Drafts', 'uncertain', false, null),
        );
        $receipts->expects(self::once())->method('markUncertain')->with($sourceKey);
        $drafts = $this->createMock(MailDraftStore::class);
        $drafts->expects(self::once())->method('append')->willThrowException(new RuntimeException('connection lost after append'));
        $drafts->expects(self::once())->method('findByMessageId')->willReturn(null);
        $writer = new SaveMailDraft($reader, $receipts, $drafts, new ReplyDraftMimeBuilder(), 'contact@example.test', 'Drafts');

        self::assertSame('uncertain', $writer->save('reference', 'Bonjour')['status']);
        self::assertSame('uncertain', $writer->save('reference', 'Texte corrigé')['status']);
    }

    public function testSavedDraftRemovedFromImapIsNotSilentlyRecreated(): void
    {
        $sourceKey = str_repeat('c', 64);
        $reader = $this->createStub(MailReader::class);
        $reader->method('replyContext')->willReturn(new ReplyContext($sourceKey, '<source@example.test>', 'Bonjour', 'marie@example.test', null, 'reference'));
        $receipts = $this->createStub(MailDraftReceiptStore::class);
        $receipts->method('reserve')->willReturn(new DraftReceipt($sourceKey, '<draft@example.test>', 'Drafts', 'saved', false, 42));
        $drafts = $this->createMock(MailDraftStore::class);
        $drafts->expects(self::never())->method('append');
        $drafts->method('findByMessageId')->willReturn(null);

        $result = (new SaveMailDraft($reader, $receipts, $drafts, new ReplyDraftMimeBuilder(), 'contact@example.test', 'Drafts'))->save('reference', 'Texte corrigé');

        self::assertSame('missing', $result['status']);
    }
}
