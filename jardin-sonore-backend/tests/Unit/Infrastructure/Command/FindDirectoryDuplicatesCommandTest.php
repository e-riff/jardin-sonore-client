<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Command;

use App\Application\Directory\DirectoryDuplicateCandidate;
use App\Application\Directory\DirectoryDuplicateCandidateLookupInterface;
use App\Infrastructure\Command\FindDirectoryDuplicatesCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class FindDirectoryDuplicatesCommandTest extends TestCase
{
    #[Test]
    public function itPrintsCandidateEvidenceWithoutOfferingToMerge(): void
    {
        $candidateLookup = $this->createMock(DirectoryDuplicateCandidateLookupInterface::class);
        $candidateLookup
            ->expects(self::once())
            ->method('findCandidates')
            ->with(25)
            ->willReturn([new DirectoryDuplicateCandidate(
                firstOrganizationId: 629,
                firstOrganizationName: 'Les Griottes',
                firstLocation: '15 Ter Allée du Clos du Centre 69690 Bessenay',
                secondOrganizationId: 2296,
                secondOrganizationName: 'CRÈCHE LES GRIOTTES',
                secondLocation: '15 Ter Allée du Clos du Centre 69690 Bessenay',
                signals: ['same_name', 'same_address', 'same_email', 'same_phone'],
            )]);

        $commandTester = new CommandTester(new FindDirectoryDuplicatesCommand($candidateLookup));
        $exitCode = $commandTester->execute(['--limit' => 25]);
        $output = $commandTester->getDisplay();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('629', $output);
        self::assertStringContainsString('2296', $output);
        self::assertStringContainsString('même adresse', $output);
        self::assertStringContainsString('Aucune fusion', $output);
    }
}
