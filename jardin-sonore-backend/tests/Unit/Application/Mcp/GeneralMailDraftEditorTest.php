<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\GeneralMailDraftEditor;
use App\Application\Mcp\MailDraft;
use App\Application\Mcp\MailDraftAttachment;
use App\Application\Mcp\MailDraftStore;
use App\Infrastructure\Mail\DraftMessageMapper;
use App\Infrastructure\Mail\DraftMimeBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Message;

final class GeneralMailDraftEditorTest extends TestCase
{
    public function testReplacementIsVerifiedThroughTheRealMimeParserWithAttachmentChanges(): void
    {
        $oldFile = new MailDraftAttachment('ancien.pdf', 'application/pdf', 'OLD');
        $draft = new MailDraft(12, '<manual@example.test>', 'Sujet', 'a@example.test', 'Bonjour', attachments: [$oldFile]);
        $replacement = null;
        $store = $this->createMock(MailDraftStore::class);
        $store->method('getByMessageId')->willReturnCallback(static function (string $folder, string $id) use ($draft, &$replacement): ?MailDraft {
            return $id === $draft->messageId ? $draft : $replacement;
        });
        $store->expects(self::once())->method('append')->willReturnCallback(static function (string $folder, string $mime) use (&$replacement): ?int {
            $message = Message::fromString($mime);
            $message->uid = 18;
            $replacement = (new DraftMessageMapper())->map($message, '<' . trim((string) $message->getMessageId(), '<>') . '>');

            return null;
        });
        $store->expects(self::once())->method('deleteByMessageId')->with('INBOX.Drafts', '<manual@example.test>', 12)->willReturn(true);
        $result = $this->editor($store)->update($draft->messageId, $draft->snapshotRevision(), [
            'body' => "Bonjour\nVoici le devis.", 'htmlBody' => "<p>Bonjour</p>\n<p>Voici le devis.</p>",
            'attachments' => [['filename' => 'nouveau.pdf', 'mimeType' => 'application/pdf', 'base64' => 'TkVX']],
            'removeAttachmentIds' => [$oldFile->metadata()['id']],
        ]);
        self::assertSame('saved', $result['status']);
        self::assertCount(1, $result['attachments']);
        self::assertSame('nouveau.pdf', $result['attachments'][0]['filename']);
        self::assertSame('NEW', $replacement->attachments[0]->content);
    }

    public function testReadsManualDraftAndListsAttachmentsWithoutExposingContent(): void
    {
        $draft = new MailDraft(12, '<thunderbird@example.test>', 'Sujet', 'a@example.test', 'Bonjour', attachments: [new MailDraftAttachment('devis.pdf', 'application/pdf', 'PDF')]);
        $store = $this->createStub(MailDraftStore::class);
        $store->method('getByMessageId')->willReturn($draft);
        $result = $this->editor($store)->read('<thunderbird@example.test>');
        self::assertSame('saved', $result['status']);
        self::assertSame('devis.pdf', $result['attachments'][0]['filename']);
        self::assertArrayNotHasKey('content', $result['attachments'][0]);
    }

    public function testPartialUpdatePreservesHtmlRecipientsAttachmentsAndThreading(): void
    {
        $attachment = new MailDraftAttachment('devis.pdf', 'application/pdf', 'PDF');
        $draft = new MailDraft(12, '<manual@example.test>', 'Sujet', 'a@example.test', 'Bonjour', '<p>Bonjour</p>', ['second@example.test'], ['cc@example.test'], ['bcc@example.test'], [$attachment], '<source@example.test>', '<root@example.test>');
        $store = $this->createMock(MailDraftStore::class);
        $replacement = null;
        $store->method('getByMessageId')->willReturnCallback(static function (string $folder, string $id) use ($draft, &$replacement): ?MailDraft {
            return $id === $draft->messageId ? $draft : $replacement;
        });
        $store->expects(self::once())->method('append')->willReturnCallback(static function (string $folder, string $mime) use (&$replacement, $attachment): ?int {
            self::assertStringContainsString('Bcc: bcc@example.test', $mime);
            self::assertStringContainsString('Cc: cc@example.test', $mime);
            self::assertStringContainsString('second@example.test', $mime);
            self::assertStringContainsString('In-Reply-To: <source@example.test>', $mime);
            self::assertStringContainsString('References: <root@example.test>', $mime);
            self::assertStringContainsString('devis.pdf', $mime);
            self::assertStringContainsString('<p>Bonjour</p>', $mime);
            preg_match('/Message-ID: (<[^>]+>)/', $mime, $matches);
            $replacement = new MailDraft(18, $matches[1], 'Nouveau sujet', 'a@example.test', 'Bonjour', '<p>Bonjour</p>', ['second@example.test'], ['cc@example.test'], ['bcc@example.test'], [$attachment], '<source@example.test>', '<root@example.test>');

            return null;
        });
        $store->expects(self::once())->method('deleteByMessageId')->with('INBOX.Drafts', '<manual@example.test>', 12)->willReturn(true);
        $result = $this->editor($store)->update($draft->messageId, $draft->snapshotRevision(), ['subject' => 'Nouveau sujet']);
        self::assertSame('saved', $result['status']);
    }

    public function testRecipientChangeOutsideMcpPreventsReplacement(): void
    {
        $before = new MailDraft(12, '<manual@example.test>', 'Sujet', 'a@example.test', 'Bonjour');
        $after = new MailDraft(12, '<manual@example.test>', 'Sujet', 'other@example.test', 'Bonjour');
        $store = $this->createMock(MailDraftStore::class);
        $store->method('getByMessageId')->willReturn($after);
        $store->expects(self::never())->method('append');
        $store->expects(self::never())->method('deleteByMessageId');
        self::assertSame('conflict', $this->editor($store)->update($before->messageId, $before->snapshotRevision(), ['body' => 'Modifié'])['status']);
    }

    public function testUnverifiedReplacementNeverDeletesOldDraft(): void
    {
        $draft = new MailDraft(12, '<manual@example.test>', 'Sujet', 'a@example.test', 'Bonjour');
        $store = $this->createMock(MailDraftStore::class);
        $store->method('getByMessageId')->willReturnCallback(static fn (string $folder, string $id): ?MailDraft => $id === $draft->messageId ? $draft : null);
        $store->expects(self::once())->method('append');
        $store->expects(self::never())->method('deleteByMessageId');
        self::assertSame('uncertain', $this->editor($store)->update($draft->messageId, $draft->snapshotRevision(), ['body' => 'Modifié'])['status']);
    }

    public function testRejectsInvalidBase64BeforeWritingAnything(): void
    {
        $draft = new MailDraft(12, '<manual@example.test>', 'Sujet', 'a@example.test', 'Bonjour');
        $store = $this->createMock(MailDraftStore::class);
        $store->method('getByMessageId')->willReturn($draft);
        $store->expects(self::never())->method('append');
        $this->expectException(InvalidArgumentException::class);
        $this->editor($store)->update($draft->messageId, $draft->snapshotRevision(), ['attachments' => [['filename' => 'x.pdf', 'mimeType' => 'application/pdf', 'base64' => '%%%']]]);
    }

    private function editor(MailDraftStore $store): GeneralMailDraftEditor
    {
        return new GeneralMailDraftEditor($store, new DraftMimeBuilder(), 'contact@example.test', 'INBOX.Drafts');
    }
}
