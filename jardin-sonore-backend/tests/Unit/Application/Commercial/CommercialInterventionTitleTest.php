<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Commercial;

use App\Application\Commercial\CommercialInterventionTitle;
use PHPUnit\Framework\TestCase;

final class CommercialInterventionTitleTest extends TestCase
{
    public function testRecognizesOptionMarkerFromGoogleTitle(): void
    {
        self::assertTrue(CommercialInterventionTitle::hasOptionMarker('[OPT] IPE - Jardin'));
        self::assertTrue(CommercialInterventionTitle::hasOptionMarker('??? IPE - Jardin'));
        self::assertFalse(CommercialInterventionTitle::hasOptionMarker('IPE - Jardin'));
    }

    public function testOptionMarkerIsAddedOnlyOnce(): void
    {
        self::assertSame('[OPT] IPE - Jardin', CommercialInterventionTitle::forOption('IPE - Jardin', true));
        self::assertSame('[OPT] IPE - Jardin', CommercialInterventionTitle::forOption('[OPT] IPE - Jardin', true));
        self::assertSame('[OPT] IPE - Jardin', CommercialInterventionTitle::forOption('??? IPE - Jardin', true));
    }

    public function testOptionMarkerIsRemovedWhenInterventionIsConfirmed(): void
    {
        self::assertSame('IPE - Jardin', CommercialInterventionTitle::forOption('[OPT] IPE - Jardin', false));
        self::assertSame('IPE - Jardin', CommercialInterventionTitle::forOption('??? IPE - Jardin', false));
    }
}
