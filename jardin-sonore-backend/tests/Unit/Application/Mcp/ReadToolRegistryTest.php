<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\MailReader;
use App\Application\Mcp\ProjectSearch;
use App\Application\Mcp\ReadToolRegistry;
use PHPUnit\Framework\TestCase;

final class ReadToolRegistryTest extends TestCase
{
    public function testExposesOnlyTheThreeReadTools(): void
    {
        $registry = new ReadToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectSearch::class));

        self::assertSame(['read_mail_thread', 'search_mail', 'search_projects'], array_column($registry->definitions(), 'name'));
        foreach ($registry->definitions() as $definition) {
            self::assertTrue($definition['annotations']['readOnlyHint']);
            self::assertFalse($definition['annotations']['destructiveHint']);
        }
    }

    public function testSearchMailValidatesAndForwardsBoundedArguments(): void
    {
        $mailReader = $this->createMock(MailReader::class);
        $mailReader->expects(self::once())
            ->method('search')
            ->with('marie@example.test', '2026-10-01', 'atelier', 20)
            ->willReturn([['subject' => 'Atelier']]);
        $registry = new ReadToolRegistry($mailReader, $this->createStub(ProjectSearch::class));

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
        $registry = new ReadToolRegistry($mailReader, $this->createStub(ProjectSearch::class));

        $result = $registry->call('search_mail', ['query' => 'atelier', 'limit' => 1000]);

        self::assertTrue($result['isError']);
        self::assertSame('Invalid search limit.', $result['content'][0]['text']);
    }

    public function testRejectsUnknownToolAndMalformedThreadReference(): void
    {
        $mailReader = $this->createMock(MailReader::class);
        $mailReader->expects(self::never())->method('readThread');
        $registry = new ReadToolRegistry($mailReader, $this->createStub(ProjectSearch::class));

        self::assertTrue($registry->call('send_mail', [])['isError']);
        self::assertTrue($registry->call('read_mail_thread', ['mailReference' => ''])['isError']);
    }

    public function testSearchRequiresAFilterAndRejectsUnexpectedArguments(): void
    {
        $mailReader = $this->createMock(MailReader::class);
        $mailReader->expects(self::never())->method('search');
        $registry = new ReadToolRegistry($mailReader, $this->createStub(ProjectSearch::class));

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
        $registry = new ReadToolRegistry($this->createStub(MailReader::class), $projectSearch);

        $result = $registry->call('search_projects', ['email' => 'marie@example.test', 'organization' => ' Les Lucioles ']);

        self::assertFalse($result['isError']);
        self::assertStringContainsString('Éveil musical', $result['content'][0]['text']);
    }
}
