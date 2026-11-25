<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\McpSessionReader;
use App\Domain\Model\Session\SessionFamily;
use App\Domain\Repository\SessionFamilyRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class McpSessionReaderTest extends TestCase
{
    public function testFindsFamilyByName(): void
    {
        $families = $this->createStub(SessionFamilyRepositoryInterface::class);
        $families->method('listFamilies')->willReturn([new SessionFamily('Automne'), new SessionFamily('Printemps')]);
        $reader = new McpSessionReader($families, $this->createStub(SessionSummaryRepositoryInterface::class), $this->createStub(UrlGeneratorInterface::class));

        self::assertSame('Automne', $reader->searchFamilies('automne', 10)[0]['name']);
        self::assertCount(1, $reader->searchFamilies('automne', 10));
    }
}
