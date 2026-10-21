<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Commercial;

use App\Application\Commercial\CommercialAppointmentTitle;
use PHPUnit\Framework\TestCase;

final class CommercialAppointmentTitleTest extends TestCase
{
    public function testRecognizesOnlyAnInitialAppointmentMarker(): void
    {
        foreach (['RDV Crèche', 'rdv: direction', 'RDV · Famille', 'RDV-Point', 'RDV—École', 'RDV/École'] as $title) {
            self::assertTrue(CommercialAppointmentTitle::hasMarker($title));
        }

        foreach (['Rendez-vous', 'RDVProjet', 'Préparer RDV'] as $title) {
            self::assertFalse(CommercialAppointmentTitle::hasMarker($title));
        }
    }

    public function testAddsTheMarkerOnlyOnce(): void
    {
        self::assertSame('RDV · Crèche', CommercialAppointmentTitle::withMarker('Crèche'));
        self::assertSame('RDV Crèche', CommercialAppointmentTitle::withMarker('RDV Crèche'));
    }
}
