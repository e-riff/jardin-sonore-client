<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Directory;

use App\Application\Directory\CulturalPlaceNormalizer;
use PHPUnit\Framework\TestCase;

final class CulturalPlaceNormalizerTest extends TestCase
{
    public function testNormalizesAYouthBookshopAndPreservesSourceDate(): void
    {
        $row = [
            'id' => 'a-unique-id', 'nom_du_lieu' => 'Librairie des enfants', 'code_departement' => '42',
            'sous_domaines' => '["Librairie"]', 'types' => '["Jeunesse"]',
            'courriels' => ['bonjour@example.test'], 'telephones' => ['0477000000'],
            'commune' => 'Saint-Étienne', 'code_commune' => '42218', 'date_modification' => '2026-10-01T10:00:00',
        ];

        $result = (new CulturalPlaceNormalizer())->normalize($row, '42');
        self::assertSame('a-unique-id', $result['item']['id']);
        self::assertSame('librairie_jeunesse', $result['item']['type']);
        self::assertTrue($result['youth']);
        self::assertSame('bonjour@example.test', $result['item']['mail']);
        self::assertSame('42218', $result['item']['codeCommune']);
        self::assertSame('2026-10-01T10:00:00', $result['sourceUpdatedAt']);
        self::assertNull((new CulturalPlaceNormalizer())->normalize($row, '43'));
    }

    public function testRecognizesMultipleSourceCategories(): void
    {
        $place = (new CulturalPlaceNormalizer())->normalize([
            'id' => 'multi', 'nom_du_lieu' => 'Espace culturel', 'code_departement' => '42',
            'sous_domaines' => '["Musée"],["Bibliothèque et médiathèque"]',
            'types' => '["Histoire"],["municipale"]',
        ], '42');
        self::assertSame('mediatheque', $place['item']['type']);
    }
}
