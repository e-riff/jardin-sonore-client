<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\MailDraftEditor;
use App\Application\Mcp\MailDraftWriter;
use App\Application\Mcp\MailReader;
use App\Application\Mcp\MailReadException;
use App\Application\Mcp\MailSource;
use App\Application\Mcp\ProjectFollowupWriter;
use App\Application\Mcp\ProjectScheduleManager;
use App\Application\Mcp\StandaloneMailDraftEditor;
use App\Application\Mcp\StandaloneMailDraftWriter;
use App\Application\Mcp\WriteToolRegistry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WriteToolRegistryTest extends TestCase
{
    public function testDisabledToolRefusesBeforeReadingMailOrWriting(): void
    {
        $mailReader = $this->createMock(MailReader::class);
        $mailReader->expects(self::never())->method('source');
        $writer = $this->createMock(ProjectFollowupWriter::class);
        $writer->expects(self::never())->method('recordSummary');
        $registry = new WriteToolRegistry($mailReader, $writer, false);

        $result = $registry->call('record_mail_summary', ['projectId' => 1, 'mailReference' => 'reference', 'summary' => 'Résumé']);

        self::assertTrue($result['isError']);
    }

    public function testProjectScheduleWritesRequireTheirOwnGateAndAnExactProject(): void
    {
        $scheduleManager = $this->createMock(ProjectScheduleManager::class);
        $scheduleManager->expects(self::once())->method('execute')->with('create_project_task', ['projectId' => 7, 'title' => 'Relancer', 'dueOn' => '2026-10-22'])->willReturn(['taskId' => 12]);
        $registry = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, projectScheduleManager: $scheduleManager);

        self::assertContains('create_project_appointment', array_column($registry->definitions(), 'name'));
        self::assertTrue($registry->call('create_project_task', ['projectId' => 7, 'title' => 'Relancer', 'dueOn' => '2026-10-22'])['isError']);

        $enabled = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, projectScheduleManager: $scheduleManager, projectScheduleWriteEnabled: true);
        self::assertTrue($enabled->call('create_project_task', ['projectId' => 7, 'title' => 'Relancer', 'dueOn' => '2026-10-22', 'mailReference' => 'unwanted'])['isError']);
        self::assertFalse($enabled->call('create_project_task', ['projectId' => 7, 'title' => 'Relancer', 'dueOn' => '2026-10-22'])['isError']);
    }

    public function testEachEnabledToolValidatesAndWritesSeparately(): void
    {
        $source = new MailSource('reference', str_repeat('a', 64), '<mail@example.test>', 'Sujet', 'marie@example.test', '2026-10-19T08:00:00+00:00');
        $mailReader = $this->createMock(MailReader::class);
        $mailReader->expects(self::exactly(2))->method('source')->with('reference')->willReturn($source);
        $writer = $this->createMock(ProjectFollowupWriter::class);
        $writer->expects(self::once())->method('recordSummary')->with(7, $source, 'Résumé')->willReturn(['eventId' => 1]);
        $writer->expects(self::once())->method('createReminder')->with(7, $source, 'Répondre', self::isInstanceOf(DateTimeImmutable::class))->willReturn(['actionId' => 2]);
        $registry = new WriteToolRegistry($mailReader, $writer, true);

        self::assertFalse($registry->call('record_mail_summary', ['projectId' => 7, 'mailReference' => 'reference', 'summary' => 'Résumé'])['isError']);
        self::assertFalse($registry->call('create_project_reminder', ['projectId' => 7, 'mailReference' => 'reference', 'title' => 'Répondre', 'dueOn' => '2026-10-20'])['isError']);
    }

    public function testInvalidArgumentsAndStaleReferenceStopBeforeWriting(): void
    {
        $mailReader = $this->createMock(MailReader::class);
        $mailReader->expects(self::once())->method('source')->willThrowException(new MailReadException('stale'));
        $writer = $this->createMock(ProjectFollowupWriter::class);
        $writer->expects(self::never())->method('recordSummary');
        $writer->expects(self::never())->method('createReminder');
        $registry = new WriteToolRegistry($mailReader, $writer, true);

        self::assertTrue($registry->call('create_project_reminder', ['projectId' => 0, 'mailReference' => 'reference', 'title' => 'Répondre', 'dueOn' => '2026-10-20'])['isError']);
        self::assertTrue($registry->call('create_project_reminder', ['projectId' => 7, 'mailReference' => 'reference', 'title' => 'Répondre', 'dueOn' => '2026-02-31'])['isError']);
        self::assertTrue($registry->call('record_mail_summary', ['projectId' => 7, 'mailReference' => 'reference', 'summary' => str_repeat('x', 10001)])['isError']);
        self::assertTrue($registry->call('record_mail_summary', ['projectId' => 7, 'mailReference' => 'reference', 'summary' => 'Résumé', 'folder' => 'Sent'])['isError']);
        self::assertTrue($registry->call('record_mail_summary', ['projectId' => 7, 'mailReference' => 'reference', 'summary' => 'Résumé'])['isError']);
    }

    public function testSummaryCanContainUpToTenThousandCharacters(): void
    {
        $source = new MailSource('reference', str_repeat('a', 64), null, 'Sujet', 'marie@example.test', '2026-10-19T08:00:00+00:00');
        $mailReader = $this->createStub(MailReader::class);
        $mailReader->method('source')->willReturn($source);
        $writer = $this->createMock(ProjectFollowupWriter::class);
        $writer->expects(self::once())->method('recordSummary')->willReturn(['eventId' => 1]);

        $result = (new WriteToolRegistry($mailReader, $writer, true))->call('record_mail_summary', [
            'projectId' => 7,
            'mailReference' => 'reference',
            'summary' => str_repeat('x', 9000),
        ]);

        self::assertFalse($result['isError']);
    }

    public function testDraftToolHasItsOwnOffByDefaultGateAndRejectsRecipientOverride(): void
    {
        $draftWriter = $this->createMock(MailDraftWriter::class);
        $draftWriter->expects(self::never())->method('save');
        $registry = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), true, $draftWriter, false);

        self::assertContains('save_mail_draft', array_column($registry->definitions(), 'name'));
        self::assertTrue($registry->call('save_mail_draft', ['mailReference' => 'reference', 'body' => 'Bonjour'])['isError']);

        $enabled = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, $draftWriter, true);
        self::assertTrue($enabled->call('save_mail_draft', ['mailReference' => 'reference', 'body' => 'Bonjour', 'to' => 'evil@example.test'])['isError']);
        self::assertTrue($enabled->call('save_mail_draft', ['mailReference' => 'reference', 'body' => str_repeat('x', 10_001)])['isError']);
    }

    public function testUncertainDraftResultIsReportedAsToolError(): void
    {
        $draftWriter = $this->createStub(MailDraftWriter::class);
        $draftWriter->method('save')->willReturn(['status' => 'uncertain', 'folder' => 'Drafts', 'messageId' => '<draft@example.test>', 'uid' => null]);
        $registry = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, $draftWriter, true);

        $result = $registry->call('save_mail_draft', ['mailReference' => 'reference', 'body' => 'Bonjour']);

        self::assertTrue($result['isError']);
        self::assertStringContainsString('uncertain', $result['content'][0]['text']);
    }

    public function testSelfSentMailExplainsWhyReplyDraftCannotBeCreated(): void
    {
        $draftWriter = $this->createMock(MailDraftWriter::class);
        $draftWriter->expects(self::once())->method('save')->willThrowException(new MailReadException('Cannot reply to a message sent by this mailbox.'));
        $registry = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, $draftWriter, true);

        $result = $registry->call('save_mail_draft', ['mailReference' => 'reference', 'body' => 'Bonjour']);

        self::assertTrue($result['isError']);
        self::assertSame('A reply draft can only be created for a message from another sender.', $result['content'][0]['text']);
    }

    public function testStandaloneDraftToolRequiresItsOwnGateAndUsesExplicitRecipient(): void
    {
        $draftWriter = $this->createMock(StandaloneMailDraftWriter::class);
        $draftWriter->expects(self::once())->method('create')->with('marie@example.test', 'Premier échange', 'Bonjour Marie')
            ->willReturn(['status' => 'saved', 'folder' => 'INBOX.Drafts', 'messageId' => '<draft@example.test>', 'uid' => 9, 'to' => 'marie@example.test', 'subject' => 'Premier échange']);
        $registry = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, null, false, null, false, $draftWriter, false);

        self::assertContains('create_mail_draft', array_column($registry->definitions(), 'name'));
        self::assertTrue($registry->call('create_mail_draft', ['recipient' => 'marie@example.test', 'subject' => 'Premier échange', 'body' => 'Bonjour Marie'])['isError']);

        $enabled = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, null, false, null, false, $draftWriter, true);
        $result = $enabled->call('create_mail_draft', ['recipient' => 'marie@example.test', 'subject' => 'Premier échange', 'body' => 'Bonjour Marie']);

        self::assertFalse($result['isError']);
    }

    public function testHtmlBodyReachesTheReplyAndStandaloneDraftTools(): void
    {
        $htmlBody = '<p>Bonjour <strong>Marie</strong></p>';
        $replyWriter = $this->createMock(MailDraftWriter::class);
        $replyWriter->expects(self::once())->method('save')->with('reference', 'Bonjour Marie', $htmlBody)->willReturn(['status' => 'saved']);
        $replyEditor = $this->createMock(MailDraftEditor::class);
        $replyEditor->expects(self::once())->method('update')->with('reference', '<reply@example.test>', str_repeat('a', 64), 'Bonjour Marie', $htmlBody)->willReturn(['status' => 'saved']);
        $standaloneWriter = $this->createMock(StandaloneMailDraftWriter::class);
        $standaloneWriter->expects(self::once())->method('create')->with('marie@example.test', 'Sujet', 'Bonjour Marie', $htmlBody)->willReturn(['status' => 'saved']);
        $standaloneEditor = $this->createMock(StandaloneMailDraftEditor::class);
        $standaloneEditor->expects(self::once())->method('update')->with('<draft@example.test>', str_repeat('b', 64), 'Bonjour Marie', $htmlBody)->willReturn(['status' => 'saved']);
        $registry = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, $replyWriter, true, $replyEditor, true, $standaloneWriter, true, standaloneMailDraftEditor: $standaloneEditor);

        self::assertFalse($registry->call('save_mail_draft', ['mailReference' => 'reference', 'body' => 'Bonjour Marie', 'htmlBody' => $htmlBody])['isError']);
        self::assertFalse($registry->call('update_mail_draft', ['mailReference' => 'reference', 'draftMessageId' => '<reply@example.test>', 'revision' => str_repeat('a', 64), 'body' => 'Bonjour Marie', 'htmlBody' => $htmlBody])['isError']);
        self::assertFalse($registry->call('create_mail_draft', ['recipient' => 'marie@example.test', 'subject' => 'Sujet', 'body' => 'Bonjour Marie', 'htmlBody' => $htmlBody])['isError']);
        self::assertFalse($registry->call('update_standalone_mail_draft', ['draftMessageId' => '<draft@example.test>', 'revision' => str_repeat('b', 64), 'body' => 'Bonjour Marie', 'htmlBody' => $htmlBody])['isError']);
    }

    public function testStandaloneDraftUpdateRequiresBothGates(): void
    {
        $standaloneEditor = $this->createMock(StandaloneMailDraftEditor::class);
        $standaloneEditor->expects(self::never())->method('update');
        $arguments = ['draftMessageId' => '<draft@example.test>', 'revision' => str_repeat('a', 64), 'body' => 'Bonjour'];
        $composeDisabled = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, draftEditEnabled: true, standaloneMailDraftEditor: $standaloneEditor);
        $editDisabled = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, draftComposeEnabled: true, standaloneMailDraftEditor: $standaloneEditor);

        self::assertTrue($composeDisabled->call('update_standalone_mail_draft', $arguments)['isError']);
        self::assertTrue($editDisabled->call('update_standalone_mail_draft', $arguments)['isError']);
    }
}
