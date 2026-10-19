<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\MailReader;
use App\Application\Mcp\MailReadException;
use App\Application\Mcp\MailSource;
use App\Application\Mcp\ProjectFollowupWriter;
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
}
