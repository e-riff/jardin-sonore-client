<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Directory;

use App\Application\Directory\CulturalPlaceMunicipalityCodeMerger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CulturalPlaceMunicipalityCodeMergerTest extends TestCase
{
    public function testAddsCodeWithoutChangingDecisionsOrSourceData(): void
    {
        $batch = ['department' => '42', 'rows' => [['item' => ['id' => 'x', 'name' => 'Lieu', 'commune' => 'ST JUST ST RAMBERT'], 'decision' => 'linked', 'organizationId' => 1426]]];
        $source = [['item' => ['id' => 'x', 'name' => 'Nouveau nom', 'commune' => 'ST JUST ST RAMBERT', 'codeCommune' => '42279']]];
        $result = (new CulturalPlaceMunicipalityCodeMerger())->merge($batch, $source);
        self::assertSame('42279', $result['rows'][0]['item']['codeCommune']);
        self::assertSame('Lieu', $result['rows'][0]['item']['name']);
        self::assertSame('linked', $result['rows'][0]['decision']);
        self::assertSame(1426, $result['rows'][0]['organizationId']);
    }

    public function testRejectsChangedSourceMunicipality(): void
    {
        $batch = ['rows' => [['item' => ['id' => 'x', 'commune' => 'MONTBRISON']]]];
        $source = [['item' => ['id' => 'x', 'commune' => 'ST JUST ST RAMBERT', 'codeCommune' => '42279']]];
        $this->expectException(RuntimeException::class);
        (new CulturalPlaceMunicipalityCodeMerger())->merge($batch, $source);
    }
}
