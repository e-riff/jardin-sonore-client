<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\DraftReceipt;
use App\Application\Mcp\MailDraft;
use App\Application\Mcp\MailDraftReceiptStore;
use App\Application\Mcp\MailDraftStore;
use App\Application\Mcp\MailReader;
use App\Application\Mcp\MailSource;
use App\Application\Mcp\ReplyContext;
use App\Application\Mcp\UpdateMailDraft;
use App\Infrastructure\Mail\ReplyDraftMimeBuilder;
use PHPUnit\Framework\TestCase;

final class UpdateMailDraftTest extends TestCase
{
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

        $result = (new UpdateMailDraft($reader, $receiptStore, $draftStore, new ReplyDraftMimeBuilder(), 'contact@example.test'))->read('reference');

        self::assertSame(['status' => 'not_found'], $result);
    }

    public function testVerifiedReplacementRemovesOnlyPreviousDraft(): void
    {
        $sourceKey = str_repeat('a', 64);
        $oldId = '<old@example.test>';
        $oldDraft = new MailDraft(12, $oldId, 'Re: Bonjour', 'marie@example.test', 'Ancien texte');
        $newId = '<mcp-edit-' . $sourceKey . '-' . hash('sha256', $oldId . "\0" . 'Texte corrigé') . '@example.test>';
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

        $result = $this->editor($sourceKey, $receiptStore, $draftStore)->update('reference', $oldId, hash('sha256', $oldDraft->body), 'Texte corrigé');

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

        $result = $this->editor($sourceKey, $receiptStore, $draftStore)->update('reference', $oldId, hash('sha256', $oldDraft->body), 'Texte corrigé');

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

        return new UpdateMailDraft($reader, $receiptStore, $draftStore, new ReplyDraftMimeBuilder(), 'contact@example.test');
    }
}
