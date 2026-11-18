<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\DraftReceipt;
use App\Application\Mcp\MailDraft;
use App\Application\Mcp\MailDraftAttachment;
use App\Application\Mcp\MailDraftReceiptStore;
use App\Application\Mcp\MailDraftStore;
use App\Application\Mcp\MailReader;
use App\Application\Mcp\MailSource;
use App\Application\Mcp\ReplyContext;
use App\Application\Mcp\UpdateMailDraft;
use App\Infrastructure\Mail\DraftMimeBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UpdateMailDraftTest extends TestCase
{
    public function testAddsAndRemovesFilesPreservingRecipientsAndCanResumeCleanup(): void
    {
        $sourceKey = str_repeat('f', 64);
        $removed = new MailDraftAttachment('old.pdf', 'application/pdf', 'OLD');
        $kept = new MailDraftAttachment('keep.txt', 'text/plain', 'KEEP');
        $added = ['filename' => 'new.pdf', 'mimeType' => 'application/pdf', 'base64' => 'TkVX'];
        $old = new MailDraft(12, '<old@example.test>', 'Re: Bonjour', 'marie@example.test', 'Bonjour', '<p>Bonjour</p>', ['other@example.test'], ['copy@example.test'], ['hidden@example.test'], [$removed, $kept], '<source@example.test>', '<parent@example.test> <source@example.test>');
        $desired = new MailDraft(18, '<pending@example.test>', $old->subject, $old->recipient, $old->body, $old->htmlBody, $old->additionalRecipients, $old->cc, $old->bcc, [$kept, new MailDraftAttachment('new.pdf', 'application/pdf', 'NEW')], $old->inReplyTo, $old->references);
        $receipt = new DraftReceipt($sourceKey, $old->messageId, 'Drafts', 'saved', false, 12);
        $pending = null;
        $appended = false;
        $receipts = $this->createMock(MailDraftReceiptStore::class);
        // Reference capture lets a retry observe the pending replacement receipt.
        $receipts->method('find')->willReturnCallback(static function () use (&$receipt): DraftReceipt { return $receipt; });
        $receipts->expects(self::once())->method('beginUpdate')->willReturnCallback(static function (string $key, string $oldId, string $newId) use (&$receipt, &$pending, $desired): bool {
            $pending = new MailDraft(18, $newId, $desired->subject, $desired->recipient, $desired->body, $desired->htmlBody, $desired->additionalRecipients, $desired->cc, $desired->bcc, $desired->attachments, $desired->inReplyTo, $desired->references);
            $receipt = new DraftReceipt($key, $oldId, 'Drafts', 'updating', false, 12, $newId);

            return true;
        });
        $receipts->expects(self::once())->method('completeUpdate')->willReturn(true);
        $store = $this->createMock(MailDraftStore::class);
        $store->method('getByMessageId')->willReturnCallback(static function (string $folder, string $id) use ($old, &$pending, &$appended): ?MailDraft {
            return $id === $old->messageId ? $old : ($appended ? $pending : null);
        });
        $store->expects(self::once())->method('append')->willReturnCallback(static function (string $folder, string $mime) use (&$appended): void {
            self::assertStringContainsString('filename=new.pdf', $mime);
            self::assertStringContainsString('filename=keep.txt', $mime);
            self::assertStringNotContainsString('filename=old.pdf', $mime);
            self::assertStringContainsString('To: marie@example.test, other@example.test', $mime);
            self::assertStringContainsString('Cc: copy@example.test', $mime);
            self::assertStringContainsString('Bcc: hidden@example.test', $mime);
            self::assertStringContainsString('In-Reply-To: <source@example.test>', $mime);
            $appended = true;
        });
        $store->expects(self::exactly(2))->method('deleteByMessageId')->with('Drafts', $old->messageId, 12)->willReturnOnConsecutiveCalls(false, true);
        $editor = $this->editor($sourceKey, $receipts, $store);
        $removedIds = [$removed->metadata()['id']];
        self::assertSame('uncertain', $editor->update('reference', $old->messageId, $old->snapshotRevision(), 'Bonjour', attachments: [$added], removeAttachmentIds: $removedIds)['status']);
        self::assertSame('update_in_progress', $editor->update('reference', $old->messageId, $old->snapshotRevision(), 'Bonjour', attachments: [array_replace($added, ['base64' => 'T1RIRVI='])], removeAttachmentIds: $removedIds)['status']);
        self::assertSame('update_in_progress', $editor->update('reference', $old->messageId, $old->snapshotRevision(), 'Bonjour', attachments: [$added])['status']);
        $result = $editor->update('reference', $old->messageId, $old->snapshotRevision(), 'Bonjour', attachments: [$added], removeAttachmentIds: $removedIds);
        self::assertSame('saved', $result['status']);
        self::assertSame(['keep.txt', 'new.pdf'], array_column($result['attachments'], 'filename'));
    }

    public function testUnknownAttachmentRemovalDoesNotReserveOrAppend(): void
    {
        $sourceKey = str_repeat('f', 64);
        $old = new MailDraft(12, '<old@example.test>', 'Re: Bonjour', 'marie@example.test', 'Bonjour');
        $receipts = $this->createMock(MailDraftReceiptStore::class);
        $receipts->method('find')->willReturn(new DraftReceipt($sourceKey, $old->messageId, 'Drafts', 'saved', false, 12));
        $receipts->expects(self::never())->method('beginUpdate');
        $store = $this->createMock(MailDraftStore::class);
        $store->method('getByMessageId')->willReturn($old);
        $store->expects(self::never())->method('append');
        $this->expectException(InvalidArgumentException::class);
        $this->editor($sourceKey, $receipts, $store)->update('reference', $old->messageId, $old->snapshotRevision(), 'Bonjour', removeAttachmentIds: [str_repeat('a', 64)]);
    }

    public function testRetryFinalizesVerifiedReplacementAfterOriginalWasAlreadyRemoved(): void
    {
        $sourceKey = str_repeat('e', 64);
        $oldDraft = new MailDraft(12, '<old@example.test>', 'Re: Bonjour', 'marie@example.test', 'Ancien texte');
        $desired = new MailDraft(18, '<pending@example.test>', 'Re: Bonjour', 'marie@example.test', 'Texte corrigé');
        $requestKey = substr(hash('sha256', $sourceKey . "\0" . $oldDraft->messageId . "\0" . $oldDraft->snapshotRevision() . "\0" . 'Texte corrigé'), 0, 24);
        $pendingId = '<mcp-edit-' . $requestKey . '-' . $desired->snapshotRevision() . '@example.test>';
        $pending = new MailDraft(18, $pendingId, $desired->subject, $desired->recipient, $desired->body);
        $receipts = $this->createMock(MailDraftReceiptStore::class);
        $receipts->method('find')->willReturn(new DraftReceipt($sourceKey, $oldDraft->messageId, 'Drafts', 'updating', false, 12, $pendingId));
        $receipts->expects(self::once())->method('completeUpdate')->with($sourceKey, $pendingId, 18)->willReturn(true);
        $store = $this->createMock(MailDraftStore::class);
        $store->method('getByMessageId')->willReturnCallback(static fn (string $folder, string $id): ?MailDraft => $id === $pendingId ? $pending : null);
        $store->expects(self::never())->method('append');
        $store->expects(self::never())->method('deleteByMessageId');
        self::assertSame('saved', $this->editor($sourceKey, $receipts, $store)->update('reference', $oldDraft->messageId, $oldDraft->snapshotRevision(), 'Texte corrigé')['status']);
    }

    public function testReadingDraftForSelfSentMessageReturnsNotFound(): void
    {
        $sourceKey = str_repeat('d', 64);
        $reader = $this->createMock(MailReader::class);
        $reader->expects(self::once())->method('source')->with('reference')->willReturn(new MailSource('reference', $sourceKey, null, 'Digest', 'contact@example.test', '2026-10-19T07:00:00+00:00'));
        $reader->expects(self::never())->method('replyContext');
        $receiptStore = $this->createMock(MailDraftReceiptStore::class);
        $receiptStore->expects(self::once())->method('find')->with($sourceKey)->willReturn(null);
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->expects(self::never())->method('getByMessageId');

        $result = (new UpdateMailDraft($reader, $receiptStore, $draftStore, new DraftMimeBuilder(), 'contact@example.test'))->read('reference');

        self::assertSame(['status' => 'not_found'], $result);
    }

    public function testVerifiedReplacementRemovesOnlyPreviousDraft(): void
    {
        $sourceKey = str_repeat('a', 64);
        $oldId = '<old@example.test>';
        $oldDraft = new MailDraft(12, $oldId, 'Re: Bonjour', 'marie@example.test', 'Ancien texte');
        $desired = new MailDraft(18, '<pending@example.test>', 'Re: Bonjour', 'marie@example.test', 'Texte corrigé');
        $requestKey = substr(hash('sha256', $sourceKey . "\0" . $oldId . "\0" . $oldDraft->snapshotRevision() . "\0" . 'Texte corrigé'), 0, 24);
        $newId = '<mcp-edit-' . $requestKey . '-' . $desired->snapshotRevision() . '@example.test>';
        $newDraft = new MailDraft(18, $newId, 'Re: Bonjour', 'marie@example.test', 'Texte corrigé');
        $receiptStore = $this->createMock(MailDraftReceiptStore::class);
        $receiptStore->expects(self::exactly(2))->method('find')->willReturnOnConsecutiveCalls(
            new DraftReceipt($sourceKey, $oldId, 'Drafts', 'saved', false, 12),
            new DraftReceipt($sourceKey, $oldId, 'Drafts', 'updating', false, 12, $newId),
        );
        $receiptStore->expects(self::once())->method('beginUpdate')->with($sourceKey, $oldId, $newId)->willReturn(true);
        $receiptStore->expects(self::once())->method('completeUpdate')->with($sourceKey, $newId, 18)->willReturn(true);
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->expects(self::exactly(5))->method('getByMessageId')->willReturnCallback(
            static function (string $folder, string $messageId) use ($oldId, $newId, $oldDraft, $newDraft): ?MailDraft {
                static $newLookupCount = 0;

                if ($oldId === $messageId) {
                    return $oldDraft;
                }
                if ($newId === $messageId) {
                    return 0 === $newLookupCount++ ? null : $newDraft;
                }

                self::fail('Unexpected draft ID.');
            },
        );
        $draftStore->expects(self::once())->method('append')->with('Drafts', self::stringContains('Texte corrig'));
        $draftStore->expects(self::once())->method('deleteByMessageId')->with('Drafts', $oldId, 12)->willReturn(true);

        $result = $this->editor($sourceKey, $receiptStore, $draftStore)->update('reference', $oldId, $oldDraft->snapshotRevision(), 'Texte corrigé');

        self::assertSame('saved', $result['status']);
        self::assertSame($newId, $result['draftMessageId']);
        self::assertSame('Texte corrigé', $result['body']);
    }

    public function testFailedReplacementVerificationKeepsPreviousDraft(): void
    {
        $sourceKey = str_repeat('b', 64);
        $oldId = '<old@example.test>';
        $oldDraft = new MailDraft(12, $oldId, 'Re: Bonjour', 'marie@example.test', 'Ancien texte');
        $receiptStore = $this->createMock(MailDraftReceiptStore::class);
        $receiptStore->method('find')->willReturnOnConsecutiveCalls(
            new DraftReceipt($sourceKey, $oldId, 'Drafts', 'saved', false, 12),
            new DraftReceipt($sourceKey, $oldId, 'Drafts', 'updating', false, 12, '<pending@example.test>'),
        );
        $receiptStore->method('beginUpdate')->willReturn(true);
        $receiptStore->expects(self::never())->method('completeUpdate');
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->method('getByMessageId')->willReturnCallback(static fn (string $folder, string $messageId): ?MailDraft => $oldId === $messageId ? $oldDraft : null);
        $draftStore->expects(self::once())->method('append');
        $draftStore->expects(self::never())->method('deleteByMessageId');

        $result = $this->editor($sourceKey, $receiptStore, $draftStore)->update('reference', $oldId, $oldDraft->snapshotRevision(), 'Texte corrigé');

        self::assertSame('uncertain', $result['status']);
    }

    public function testChangedDraftRevisionPreventsReplacement(): void
    {
        $sourceKey = str_repeat('c', 64);
        $receiptStore = $this->createStub(MailDraftReceiptStore::class);
        $receiptStore->method('find')->willReturn(new DraftReceipt($sourceKey, '<old@example.test>', 'Drafts', 'saved', false, 12));
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->method('getByMessageId')->willReturn(new MailDraft(12, '<old@example.test>', 'Re: Bonjour', 'marie@example.test', 'Modification Thunderbird'));
        $draftStore->expects(self::never())->method('append');
        $draftStore->expects(self::never())->method('deleteByMessageId');

        $result = $this->editor($sourceKey, $receiptStore, $draftStore)->update('reference', '<old@example.test>', hash('sha256', 'Ancien texte'), 'Texte corrigé');

        self::assertSame('conflict', $result['status']);
    }

    private function editor(string $sourceKey, MailDraftReceiptStore $receiptStore, MailDraftStore $draftStore): UpdateMailDraft
    {
        $reader = $this->createStub(MailReader::class);
        $reader->method('replyContext')->willReturn(new ReplyContext($sourceKey, '<source@example.test>', 'Bonjour', 'marie@example.test', null, 'reference'));

        return new UpdateMailDraft($reader, $receiptStore, $draftStore, new DraftMimeBuilder(), 'contact@example.test');
    }
}
