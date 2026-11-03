<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Directory;

use App\Application\Directory\CulturalPlaceWorkFile;
use PHPUnit\Framework\TestCase;

final class CulturalPlaceWorkFileTest extends TestCase
{
    public function testDecisionsSurviveReload(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cultural-');
        self::assertIsString($path);
        $file = new CulturalPlaceWorkFile();
        $file->save($path, ['department' => '42', 'rows' => [['item' => ['id' => 'a'], 'decision' => 'skip']]]);
        self::assertSame('skip', $file->load($path)['rows'][0]['decision']);
        unlink($path);
    }
}
