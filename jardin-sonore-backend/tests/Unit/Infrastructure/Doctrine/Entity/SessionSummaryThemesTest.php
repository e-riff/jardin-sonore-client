<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use PHPUnit\Framework\TestCase;

final class SessionSummaryThemesTest extends TestCase
{
    public function testItKeepsSeveralDistinctThemes(): void
    {
        $rainThemeEntity = (new ThemeEntity())->setLabel('Pluie')->setColor('#2563eb');
        $nightThemeEntity = (new ThemeEntity())->setLabel('Nuit')->setColor('#312e81');
        $sessionSummaryEntity = new SessionSummaryEntity();

        $sessionSummaryEntity
            ->addTheme($rainThemeEntity)
            ->addTheme($nightThemeEntity)
            ->addTheme($rainThemeEntity);

        self::assertSame([$rainThemeEntity, $nightThemeEntity], $sessionSummaryEntity->getThemes()->toArray());
    }
}
