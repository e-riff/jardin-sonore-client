<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\MailReader;
use App\Application\Mcp\ProjectContextReader;
use App\Application\Mcp\ProjectScheduleManager;
use App\Application\Mcp\ProjectSearch;
use App\Application\Mcp\ReadToolRegistry;
use App\Application\Mcp\StandaloneMailDraftEditor;
use PHPUnit\Framework\TestCase;

final class ReadToolRegistryTest extends TestCase
{
    public function testExposesProjectContextAsAReadTool(): void
    {
        $registry = new ReadToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectSearch::class), $this->createStub(ProjectContextReader::class));

        self::assertContains('get_project_context', array_column($registry->definitions(), 'name'));
        foreach ($registry->definitions() as $definition) {
            self::assertTrue($definition['annotations']['readOnlyHint']);
            self::assertFalse($definition['annotations']['destructiveHint']);
        }
    }

    public function testReadsStandaloneDraftByExactMessageId(): void
    {
        $draftEditor = $this->createMock(StandaloneMailDraftEditor::class);
        $draftEditor->expects(self::once())->method('read')->with('<draft@example.test>')->willReturn(['status' => 'saved', 'body' => 'Bonjour', 'htmlBody' => '<p>Bonjour</p>']);
        $registry = new ReadToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectSearch::class), $this->createStub(ProjectContextReader::class), standaloneMailDraftEditor: $draftEditor);

        self::assertTrue($registry->call('read_standalone_mail_draft', ['draftMessageId' => '<draft@example.test>', 'extra' => true])['isError']);
        $result = $registry->call('read_standalone_mail_draft', ['draftMessageId' => '<draft@example.test>']);

        self::assertFalse($result['isError']);
        self::assertStringContainsString('<p>Bonjour</p>', $result['content'][0]['text']);
    }

    public function testAppointmentReadRequiresExactDossierAndAppointmentIds(): void
    {
        $scheduleManager = $this->createMock(ProjectScheduleManager::class);
        $scheduleManager->expects(self::once())->method('getAppointment')->with(7, 21)->willReturn(['projectId' => 7, 'appointmentId' => 21, 'revision' => 2]);
        $registry = new ReadToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectSearch::class), $this->createStub(ProjectContextReader::class), null, null, $scheduleManager);

        self::assertTrue($registry->call('get_project_appointment', ['projectId' => 7, 'appointmentId' => '21'])['isError']);
        self::assertTrue($registry->call('get_project_appointment', ['projectId' => 7, 'appointmentId' => 21, 'extra' => true])['isError']);
        self::assertFalse($registry->call('get_project_appointment', ['projectId' => 7, 'appointmentId' => 21])['isError']);
    }

    public function testSearchMailValidatesAndForwardsBoundedArguments(): void
    {
        $mailReader = $this->createMock(MailReader::class);
        $mailReader->expects(self::once())
            ->method('search')
            ->with('marie@example.test', '2026-10-01', 'atelier', 20)
            ->willReturn([['subject' => 'Atelier']]);
        $registry = new ReadToolRegistry($mailReader, $this->createStub(ProjectSearch::class), $this->createStub(ProjectContextReader::class));

        $result = $registry->call('search_mail', [
            'from' => 'marie@example.test',
            'since' => '2026-10-01',
            'query' => 'atelier',
            'limit' => 20,
        ]);

        self::assertFalse($result['isError']);
        self::assertStringContainsString('Atelier', $result['content'][0]['text']);
    }

    public function testRejectsOutOfRangeLimitsWithoutCallingMailReader(): void
    {
        $mailReader = $this->createMock(MailReader::class);
        $mailReader->expects(self::never())->method('search');
        $registry = new ReadToolRegistry($mailReader, $this->createStub(ProjectSearch::class), $this->createStub(ProjectContextReader::class));

        $result = $registry->call('search_mail', ['query' => 'atelier', 'limit' => 1000]);

        self::assertTrue($result['isError']);
        self::assertSame('Invalid search limit.', $result['content'][0]['text']);
    }

    public function testRejectsUnknownToolAndMalformedThreadReference(): void
    {
        $mailReader = $this->createMock(MailReader::class);
        $mailReader->expects(self::never())->method('readThread');
        $registry = new ReadToolRegistry($mailReader, $this->createStub(ProjectSearch::class), $this->createStub(ProjectContextReader::class));

        self::assertTrue($registry->call('send_mail', [])['isError']);
        self::assertTrue($registry->call('read_mail_thread', ['mailReference' => ''])['isError']);
    }

    public function testSearchRequiresAFilterAndRejectsUnexpectedArguments(): void
    {
        $mailReader = $this->createMock(MailReader::class);
        $mailReader->expects(self::never())->method('search');
        $registry = new ReadToolRegistry($mailReader, $this->createStub(ProjectSearch::class), $this->createStub(ProjectContextReader::class));

        self::assertSame('Add a sender, date or search term.', $registry->call('search_mail', [])['content'][0]['text']);
        self::assertSame('Unexpected argument.', $registry->call('search_mail', ['query' => 'atelier', 'folder' => 'INBOX'])['content'][0]['text']);
    }

    public function testSearchProjectsForwardsOnlyValidBoundedCriteria(): void
    {
        $projectSearch = $this->createMock(ProjectSearch::class);
        $projectSearch->expects(self::once())
            ->method('search')
            ->with('marie@example.test', 'Les Lucioles', null, 10)
            ->willReturn([['id' => 17, 'title' => 'Éveil musical']]);
        $registry = new ReadToolRegistry($this->createStub(MailReader::class), $projectSearch, $this->createStub(ProjectContextReader::class));

        $result = $registry->call('search_projects', ['email' => 'marie@example.test', 'organization' => ' Les Lucioles ']);

        self::assertFalse($result['isError']);
        self::assertStringContainsString('Éveil musical', $result['content'][0]['text']);
    }

    public function testProjectContextRequiresAnExactPositiveId(): void
    {
        $contextReader = $this->createMock(ProjectContextReader::class);
        $contextReader->expects(self::once())->method('get')->with(7)->willReturn(['id' => 7, 'title' => 'Ateliers']);
        $registry = new ReadToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectSearch::class), $contextReader);

        self::assertTrue($registry->call('get_project_context', ['projectId' => '7'])['isError']);
        self::assertTrue($registry->call('get_project_context', ['projectId' => 0])['isError']);
        self::assertFalse($registry->call('get_project_context', ['projectId' => 7])['isError']);
    }

    public function testFindSentRepliesUsesAnExactMailReferenceAndThreeResultLimit(): void
    {
        $mailReader = $this->createMock(MailReader::class);
        $mailReader->expects(self::once())->method('findSentReplies')->with('mail-ref', 3)->willReturn(['matchable' => true, 'replies' => []]);
        $registry = new ReadToolRegistry($mailReader, $this->createStub(ProjectSearch::class), $this->createStub(ProjectContextReader::class));

        self::assertFalse($registry->call('find_sent_replies', ['mailReference' => 'mail-ref'])['isError']);
        self::assertTrue($registry->call('find_sent_replies', ['mailReference' => 'mail-ref', 'folder' => 'Sent'])['isError']);
    }
}
