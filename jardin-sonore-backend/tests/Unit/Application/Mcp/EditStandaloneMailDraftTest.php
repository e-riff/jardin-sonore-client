<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\EditStandaloneMailDraft;
use App\Application\Mcp\MailDraft;
use App\Application\Mcp\MailDraftStore;
use App\Infrastructure\Mail\StandaloneDraftMimeBuilder;
use PHPUnit\Framework\TestCase;

final class EditStandaloneMailDraftTest extends TestCase
{
    private const string DRAFT_ID = '<mcp-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa@example.test>';

    public function testReadsAnExistingStandaloneDraftWithRevision(): void
    {
        $draft = new MailDraft(12, self::DRAFT_ID, 'Premier échange', 'marie@example.test', 'Bonjour', '<p>Bonjour</p>');
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->expects(self::once())->method('getByMessageId')->with('INBOX.Drafts', self::DRAFT_ID)->willReturn($draft);

        $result = $this->editor($draftStore)->read(self::DRAFT_ID);

        self::assertSame($draft->revision(), $result['revision']);
        self::assertSame('<p>Bonjour</p>', $result['htmlBody']);
        self::assertSame('marie@example.test', $result['to']);
    }

    public function testChangedRevisionKeepsExistingDraft(): void
    {
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->method('getByMessageId')->willReturn(new MailDraft(12, self::DRAFT_ID, 'Premier échange', 'marie@example.test', 'Texte modifié dans Thunderbird'));
        $draftStore->expects(self::never())->method('append');
        $draftStore->expects(self::never())->method('deleteByMessageId');

        $result = $this->editor($draftStore)->update(self::DRAFT_ID, hash('sha256', 'Ancien texte'), 'Nouveau texte');

        self::assertSame('conflict', $result['status']);
    }

    public function testVerifiedReplacementPreservesRecipientAndReplacesOnlyTheOldUid(): void
    {
        $oldDraft = new MailDraft(12, self::DRAFT_ID, 'Premier échange', 'marie@example.test', 'Bonjour');
        $newHtml = '<p>Bonjour <strong>Marie</strong></p>';
        $newId = '<mcp-standalone-edit-' . hash('sha256', self::DRAFT_ID . "\0" . 'Bonjour Marie' . "\0" . $newHtml) . '@example.test>';
        $newDraft = new MailDraft(18, $newId, 'Premier échange', 'marie@example.test', 'Bonjour Marie', $newHtml);
        $draftStore = $this->createMock(MailDraftStore::class);
        $draftStore->method('getByMessageId')->willReturnCallback(static function (string $folder, string $messageId) use ($oldDraft, $newDraft, $newId): ?MailDraft {
            static $pendingLookups = 0;

            if (self::DRAFT_ID === $messageId) {
                return $oldDraft;
            }
            if ($newId === $messageId) {
                return 0 === $pendingLookups++ ? null : $newDraft;
            }

            self::fail('Unexpected draft identifier.');
        });
        $draftStore->expects(self::once())->method('append')->with('INBOX.Drafts', self::stringContains('multipart/alternative'));
        $draftStore->expects(self::once())->method('deleteByMessageId')->with('INBOX.Drafts', self::DRAFT_ID, 12)->willReturn(true);

        $result = $this->editor($draftStore)->update(self::DRAFT_ID, $oldDraft->revision(), 'Bonjour Marie', $newHtml);

        self::assertSame('saved', $result['status']);
        self::assertSame($newId, $result['draftMessageId']);
        self::assertSame($newHtml, $result['htmlBody']);
    }

    private function editor(MailDraftStore $draftStore): EditStandaloneMailDraft
    {
        return new EditStandaloneMailDraft($draftStore, new StandaloneDraftMimeBuilder(), 'contact@example.test', 'INBOX.Drafts');
    }
}
